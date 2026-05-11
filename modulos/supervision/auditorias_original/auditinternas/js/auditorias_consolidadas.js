// Función para alternar la visibilidad del filtro de mes/año
function toggleFiltroMesAnio() {
    const filtro = document.getElementById('filtro-mes-anio');
    if (filtro) {
        filtro.classList.toggle('activo');
    }
}

// Función para cerrar el filtro de mes/año
function cerrarFiltroMesAnio() {
    const filtro = document.getElementById('filtro-mes-anio');
    if (filtro) {
        filtro.classList.remove('activo');
    }
}

// Cerrar el filtro si se hace clic fuera de él
document.addEventListener('click', function(event) {
    const filtro = document.getElementById('filtro-mes-anio');
    const target = event.target;
    
    // Si el clic no fue dentro del filtro ni en el botón que lo activa
    if (filtro && !filtro.contains(target) && !target.closest('.filtro-encabezado')) {
        filtro.classList.remove('activo');
    }
});

// Elementos del DOM
const operarioInput = document.getElementById('operario');
const operarioIdInput = document.getElementById('operario_id');
const sugerenciasDiv = document.getElementById('operarios-sugerencias');

// Función para buscar operarios
function buscarOperarios(texto) {
    if (!texto) return [];
    const textoLower = texto.toLowerCase();
    return operariosData.filter(op => 
        op.nombre.toLowerCase().includes(textoLower)
    );
}

// Función para mostrar sugerencias
function mostrarSugerencias(resultados) {
    if (!sugerenciasDiv) return;
    
    sugerenciasDiv.innerHTML = '';
    
    if (resultados.length === 0) {
        sugerenciasDiv.style.display = 'none';
        return;
    }
    
    resultados.forEach(op => {
        const div = document.createElement('div');
        div.textContent = op.nombre;
        div.className = 'sugerencia-item';
        div.addEventListener('click', () => {
            if (operarioInput) operarioInput.value = op.nombre;
            if (operarioIdInput) operarioIdInput.value = op.id;
            sugerenciasDiv.style.display = 'none';
        });
        sugerenciasDiv.appendChild(div);
    });
    
    sugerenciasDiv.style.display = 'block';
}

// Event Listeners
if (operarioInput && sugerenciasDiv) {
    operarioInput.addEventListener('input', function() {
        const texto = this.value.trim();
        if (texto.length >= 2) {
            mostrarSugerencias(buscarOperarios(texto));
        } else {
            sugerenciasDiv.style.display = 'none';
        }
    });

    operarioInput.addEventListener('focus', function() {
        if (this.value.trim() === '') {
            if (typeof operariosData !== 'undefined') {
                mostrarSugerencias(operariosData.slice(0, 10)); // Muestra los primeros 10 por defecto
            }
        }
    });
}

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', function(e) {
    if (operarioInput && sugerenciasDiv) {
        if (!operarioInput.contains(e.target) && !sugerenciasDiv.contains(e.target)) {
            sugerenciasDiv.style.display = 'none';
        }
    }
});

// Validación de fechas
const fechaDesde = document.getElementById('fecha_desde');
const fechaHasta = document.getElementById('fecha_hasta');

if (fechaDesde && fechaHasta) {
    fechaDesde.addEventListener('change', function() {
        if (this.value && fechaHasta.value && this.value > fechaHasta.value) {
            fechaHasta.value = this.value;
        }
    });

    fechaHasta.addEventListener('change', function() {
        if (this.value && fechaDesde.value && this.value < fechaDesde.value) {
            fechaDesde.value = this.value;
        }
    });
}
