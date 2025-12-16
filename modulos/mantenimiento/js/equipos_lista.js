// public_html/modulos/mantenimiento/js/equipos_lista.js

let equiposOriginales = [];
let equiposFiltrados = [];
let filtrosActivos = {};

// Cargar equipos al iniciar
document.addEventListener('DOMContentLoaded', function() {
    cargarEquipos();
});

// Cargar equipos desde el servidor
async function cargarEquipos() {
    try {
        const response = await fetch('ajax/equipos_listar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cargo: cargoOperario,
                sucursal: sucursalUsuario
            })
        });

        const data = await response.json();
        
        if (data.success) {
            equiposOriginales = data.equipos;
            equiposFiltrados = [...equiposOriginales];
            renderizarTabla();
            inicializarFiltros();
        } else {
            mostrarError(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarError('Error al cargar los equipos');
    }
}

// Renderizar tabla
function renderizarTabla() {
    const tbody = document.getElementById('cuerpoTabla');
    
    if (equiposFiltrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="texto-centrado">No se encontraron equipos</td></tr>';
        return;
    }

    let html = '';
    equiposFiltrados.forEach(equipo => {
        const estadoBadge = obtenerEstadoBadge(equipo);
        const proximoMtto = calcularProximoMantenimiento(equipo);
        
        html += `
            <tr>
                <td>${equipo.codigo}</td>
                <td>${equipo.nombre}</td>
                <td>${equipo.tipo_nombre}</td>
                <td>${equipo.ubicacion_actual || 'Sin ubicación'}</td>
                <td>${equipo.ultimo_mantenimiento || 'Sin mantenimiento'}</td>
                <td>
                    ${proximoMtto.texto}
                    ${equipo.solicitud_pendiente ? 
                        `<br><small class="badge badge-agendado">Movimiento: ${equipo.fecha_movimiento}</small>` 
                        : ''}
                </td>
                <td>${estadoBadge}</td>
                <td>
                    <button onclick="verDashboard(${equipo.id})" class="btn btn-pequeno btn-icono" title="Ver Dashboard">
                        📊
                    </button>
                    ${(!esLiderInfraestructura || cargoOperario == 5 || cargoOperario == 43) ? 
                        `<button onclick="solicitarMantenimiento(${equipo.id})" class="btn btn-pequeno btn-primario" title="Solicitar Mantenimiento">
                            🔧
                        </button>` 
                        : ''}
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Calcular próximo mantenimiento
function calcularProximoMantenimiento(equipo) {
    if (!equipo.ultimo_mantenimiento) {
        return {
            texto: '<span style="color: #dc3545;">Nunca ha tenido mtto</span>',
            clase: 'retrasado'
        };
    }

    const ultimaFecha = new Date(equipo.ultimo_mantenimiento);
    const frecuenciaMeses = equipo.frecuencia_mantenimiento_meses || 3;
    const proximaFecha = new Date(ultimaFecha);
    proximaFecha.setMonth(proximaFecha.getMonth() + frecuenciaMeses);

    const hoy = new Date();
    const diferenciaDias = Math.floor((proximaFecha - hoy) / (1000 * 60 * 60 * 24));

    let clase = '';
    let color = '';
    
    if (diferenciaDias < 0) {
        color = '#dc3545';
        clase = 'retrasado';
    } else if (diferenciaDias <= 30) {
        color = '#ffc107';
        clase = 'proximo';
    } else {
        color = '#28a745';
        clase = 'al-dia';
    }

    return {
        texto: `<span style="color: ${color};">${proximaFecha.toLocaleDateString('es-ES')}</span>`,
        clase: clase
    };
}

// Obtener badge de estado
function obtenerEstadoBadge(equipo) {
    if (equipo.en_mantenimiento) {
        return '<span class="badge badge-proceso">En Mantenimiento</span>';
    }
    if (equipo.solicitud_pendiente) {
        return '<span class="badge badge-agendado">Mantenimiento Agendado</span>';
    }
    return '<span class="badge badge-completado">Operativo</span>';
}

// Inicializar filtros
function inicializarFiltros() {
    const columnas = ['codigo', 'nombre', 'tipo', 'ubicacion', 'ultimo_mtto', 'proximo_mtto', 'estado'];
    
    columnas.forEach(columna => {
        crearFiltroDropdown(columna);
    });
}

// Crear dropdown de filtro
function crearFiltroDropdown(columna) {
    const dropdown = document.getElementById(`filtro-${columna}`);
    
    // Obtener valores únicos
    const valoresUnicos = [...new Set(equiposOriginales.map(eq => {
        switch(columna) {
            case 'codigo': return eq.codigo;
            case 'nombre': return eq.nombre;
            case 'tipo': return eq.tipo_nombre;
            case 'ubicacion': return eq.ubicacion_actual || 'Sin ubicación';
            case 'ultimo_mtto': return eq.ultimo_mantenimiento || 'Sin mantenimiento';
            case 'proximo_mtto': return calcularProximoMantenimiento(eq).texto;
            case 'estado': return eq.en_mantenimiento ? 'En Mantenimiento' : 
                                 eq.solicitud_pendiente ? 'Mantenimiento Agendado' : 'Operativo';
        }
    }))].sort();

    let html = `
        <div class="filtro-botones">
            <button onclick="ordenar('${columna}', 'asc')">▲ ASC</button>
            <button onclick="ordenar('${columna}', 'desc')">▼ DESC</button>
        </div>
        <button class="btn-limpiar-filtros" onclick="limpiarFiltro('${columna}')">Limpiar</button>
        <div style="margin: 10px 0; font-weight: bold;">Filtrar por:</div>
        <input type="text" class="filtro-busqueda" placeholder="Buscar..." 
               onkeyup="buscarEnFiltro('${columna}', this.value)">
        <div class="filtro-opciones" id="opciones-${columna}">
    `;

    valoresUnicos.forEach(valor => {
        const valorLimpio = valor.toString().replace(/"/g, '&quot;');
        html += `
            <label class="filtro-opcion">
                <input type="checkbox" value="${valorLimpio}" 
                       onchange="aplicarFiltro('${columna}')">
                ${valor}
            </label>
        `;
    });

    html += '</div>';
    dropdown.innerHTML = html;
}

// Toggle filtro
function toggleFiltro(columna) {
    const dropdown = document.getElementById(`filtro-${columna}`);
    const todosDropdowns = document.querySelectorAll('.filtro-dropdown');
    
    todosDropdowns.forEach(d => {
        if (d.id !== `filtro-${columna}`) {
            d.classList.remove('activo');
        }
    });
    
    dropdown.classList.toggle('activo');
}

// Cerrar filtros al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.filtro-columna')) {
        document.querySelectorAll('.filtro-dropdown').forEach(d => {
            d.classList.remove('activo');
        });
    }
});

// Buscar en filtro
function buscarEnFiltro(columna, texto) {
    const opciones = document.getElementById(`opciones-${columna}`).querySelectorAll('.filtro-opcion');
    const textoLower = texto.toLowerCase();
    
    opciones.forEach(opcion => {
        const label = opcion.textContent.toLowerCase();
        opcion.style.display = label.includes(textoLower) ? 'flex' : 'none';
    });
}

// Aplicar filtro
function aplicarFiltro(columna) {
    const checkboxes = document.getElementById(`opciones-${columna}`).querySelectorAll('input[type="checkbox"]:checked');
    const valoresSeleccionados = Array.from(checkboxes).map(cb => cb.value);
    
    if (valoresSeleccionados.length > 0) {
        filtrosActivos[columna] = valoresSeleccionados;
    } else {
        delete filtrosActivos[columna];
    }
    
    aplicarTodosFiltros();
}

// Aplicar todos los filtros
function aplicarTodosFiltros() {
    equiposFiltrados = equiposOriginales.filter(equipo => {
        for (let columna in filtrosActivos) {
            let valorEquipo;
            
            switch(columna) {
                case 'codigo': valorEquipo = equipo.codigo; break;
                case 'nombre': valorEquipo = equipo.nombre; break;
                case 'tipo': valorEquipo = equipo.tipo_nombre; break;
                case 'ubicacion': valorEquipo = equipo.ubicacion_actual || 'Sin ubicación'; break;
                case 'ultimo_mtto': valorEquipo = equipo.ultimo_mantenimiento || 'Sin mantenimiento'; break;
                case 'proximo_mtto': valorEquipo = calcularProximoMantenimiento(equipo).texto; break;
                case 'estado': 
                    valorEquipo = equipo.en_mantenimiento ? 'En Mantenimiento' : 
                                 equipo.solicitud_pendiente ? 'Mantenimiento Agendado' : 'Operativo';
                    break;
            }
            
            if (!filtrosActivos[columna].includes(valorEquipo.toString())) {
                return false;
            }
        }
        return true;
    });
    
    renderizarTabla();
}

// Limpiar filtro
function limpiarFiltro(columna) {
    const checkboxes = document.getElementById(`opciones-${columna}`).querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    delete filtrosActivos[columna];
    aplicarTodosFiltros();
}

// Ordenar
function ordenar(columna, direccion) {
    equiposFiltrados.sort((a, b) => {
        let valorA, valorB;
        
        switch(columna) {
            case 'codigo': valorA = a.codigo; valorB = b.codigo; break;
            case 'nombre': valorA = a.nombre; valorB = b.nombre; break;
            case 'tipo': valorA = a.tipo_nombre; valorB = b.tipo_nombre; break;
            case 'ubicacion': 
                valorA = a.ubicacion_actual || 'Sin ubicación'; 
                valorB = b.ubicacion_actual || 'Sin ubicación'; 
                break;
        }
        
        if (direccion === 'asc') {
            return valorA > valorB ? 1 : -1;
        } else {
            return valorA < valorB ? 1 : -1;
        }
    });
    
    renderizarTabla();
}

// Ver dashboard
function verDashboard(equipoId) {
    window.location.href = `equipos_dashboard.php?id=${equipoId}`;
}

// Solicitar mantenimiento
function solicitarMantenimiento(equipoId) {
    window.location.href = `equipos_solicitud_mantenimiento.php?equipo_id=${equipoId}`;
}

// Mostrar error
function mostrarError(mensaje) {
    const tbody = document.getElementById('cuerpoTabla');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="texto-centrado">
                <div class="alerta alerta-error">${mensaje}</div>
            </td>
        </tr>
    `;
}