// Variables globales
let celdaEnEdicion = null;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    inicializarEventos();
});

// Inicializar eventos de las celdas editables
function inicializarEventos() {
    const celdasEditables = document.querySelectorAll('td[data-editable="true"]');
    
    celdasEditables.forEach(celda => {
        celda.addEventListener('click', function() {
            if (celdaEnEdicion && celdaEnEdicion !== this) {
                cancelarEdicion(celdaEnEdicion);
            }
            activarEdicion(this);
        });
    });
}

// Activar modo edición en una celda
function activarEdicion(celda) {
    if (celdaEnEdicion === celda) return;
    
    const valorDisplay = celda.querySelector('.valor-display');
    const input = celda.querySelector('.input-inline');
    
    if (!input) return;
    
    // Ocultar el display y mostrar el input
    valorDisplay.style.display = 'none';
    input.style.display = 'block';
    celda.classList.add('editando');
    celdaEnEdicion = celda;
    
    // Focus en el input
    input.focus();
    input.select();
    
    // Eventos del input
    input.addEventListener('blur', function() {
        guardarValor(celda);
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            guardarValor(celda);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelarEdicion(celda);
        }
    });
}

// Cancelar edición sin guardar
function cancelarEdicion(celda) {
    if (!celda) return;
    
    const valorDisplay = celda.querySelector('.valor-display');
    const input = celda.querySelector('.input-inline');
    
    if (input && valorDisplay) {
        input.style.display = 'none';
        valorDisplay.style.display = 'inline-block';
        celda.classList.remove('editando');
    }
    
    celdaEnEdicion = null;
}

// Guardar valor editado
function guardarValor(celda) {
    const input = celda.querySelector('.input-inline');
    const valorDisplay = celda.querySelector('.valor-display');
    
    if (!input) return;
    
    const nuevoValor = input.value.trim();
    const idIndicador = celda.dataset.id;
    const semana = celda.dataset.semana;
    const tipo = celda.dataset.tipo;
    const divide = celda.dataset.divide;
    
    // Validación para denominador cero
    if (tipo === 'denominador' && parseFloat(nuevoValor) === 0) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'El denominador no puede ser cero',
            confirmButtonColor: '#51B8AC'
        });
        input.focus();
        return;
    }
    
    // Preparar datos para enviar
    let datos = {
        id_indicador: idIndicador,
        semana: semana,
        tipo: tipo,
        divide: divide
    };
    
    if (tipo === 'unico') {
        datos.numerador = nuevoValor !== '' ? nuevoValor : null;
        datos.denominador = null;
    } else if (tipo === 'numerador') {
        datos.numerador = nuevoValor !== '' ? nuevoValor : null;
        // Obtener el valor actual del denominador
        const filaDenominador = encontrarFilaDenominador(celda);
        if (filaDenominador) {
            const celdaDenominador = filaDenominador.querySelector(`td[data-semana="${semana}"]`);
            if (celdaDenominador) {
                const inputDen = celdaDenominador.querySelector('.input-inline');
                const valorDen = inputDen ? inputDen.value : celdaDenominador.querySelector('.valor-display').textContent.replace(/\./g, '').replace(',', '.').replace('--', '');
                datos.denominador = valorDen !== '' ? valorDen : null;
            }
        }
    } else if (tipo === 'denominador') {
        datos.denominador = nuevoValor !== '' ? nuevoValor : null;
        // Obtener el valor actual del numerador
        const filaNumerador = encontrarFilaNumerador(celda);
        if (filaNumerador) {
            const celdaNumerador = filaNumerador.querySelector(`td[data-semana="${semana}"]`);
            if (celdaNumerador) {
                const inputNum = celdaNumerador.querySelector('.input-inline');
                const valorNum = inputNum ? inputNum.value : celdaNumerador.querySelector('.valor-display').textContent.replace(/\./g, '').replace(',', '.').replace('--', '');
                datos.numerador = valorNum !== '' ? valorNum : null;
            }
        }
    }
    
    // Añadir spinner al display
    valorDisplay.innerHTML = '<span class="spinner"></span>';
    input.style.display = 'none';
    valorDisplay.style.display = 'inline-block';
    
    // Enviar datos via AJAX
    enviarDatos(datos, celda);
}

// Enviar datos al servidor
function enviarDatos(datos, celda) {
    fetch('ajax/guardar_indicador.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(datos)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar el display con el nuevo valor
            actualizarCelda(celda, data.valor);
            
            // Si es numerador o denominador, actualizar el resultado
            if (datos.tipo !== 'unico' && datos.divide == 1) {
                actualizarResultado(datos.id_indicador, datos.semana, data.resultado);
            }
            
            // Mostrar mensaje de éxito
            mostrarMensaje('Datos guardados exitosamente', 'success');
        } else {
            mostrarMensaje(data.message || 'Error al guardar los datos', 'error');
            // Revertir al valor anterior
            const valorAnterior = celda.querySelector('.input-inline').defaultValue;
            actualizarCelda(celda, valorAnterior);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error de conexión al guardar', 'error');
        // Revertir al valor anterior
        const valorAnterior = celda.querySelector('.input-inline').defaultValue;
        actualizarCelda(celda, valorAnterior);
    })
    .finally(() => {
        celda.classList.remove('editando');
        celdaEnEdicion = null;
    });
}

// Actualizar el contenido de una celda
function actualizarCelda(celda, valor) {
    const valorDisplay = celda.querySelector('.valor-display');
    const input = celda.querySelector('.input-inline');
    
    const valorFormateado = valor !== null && valor !== '' ? 
        parseFloat(valor).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '--';
    
    valorDisplay.textContent = valorFormateado;
    if (input) {
        input.value = valor !== null ? valor : '';
        input.defaultValue = valor !== null ? valor : '';
    }
}

// Actualizar la celda de resultado
function actualizarResultado(idIndicador, semana, resultado) {
    const celdaResultado = document.querySelector(`td[data-tipo="resultado"][data-id="${idIndicador}"][data-semana="${semana}"]`);
    
    if (celdaResultado) {
        const valorFormateado = resultado !== null ? 
            parseFloat(resultado).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' %' : '-- %';
        celdaResultado.textContent = valorFormateado;
    }
}

// Encontrar la fila del denominador desde la fila del numerador
function encontrarFilaDenominador(celdaNumerador) {
    const filaNumerador = celdaNumerador.closest('tr');
    const filaDenominador = filaNumerador.nextElementSibling;
    
    if (filaDenominador && filaDenominador.classList.contains('fila-denominador')) {
        return filaDenominador;
    }
    return null;
}

// Encontrar la fila del numerador desde la fila del denominador
function encontrarFilaNumerador(celdaDenominador) {
    const filaDenominador = celdaDenominador.closest('tr');
    const filaNumerador = filaDenominador.previousElementSibling;
    
    if (filaNumerador && filaNumerador.classList.contains('fila-numerador')) {
        return filaNumerador;
    }
    return null;
}

function mostrarMensaje(texto, tipo = 'success') {
    const mensaje = document.getElementById('mensajeSuccess');
    const textoSpan = document.getElementById('mensajeTexto');
    
    // Si no existen los elementos, usar SweetAlert2
    if (!mensaje || !textoSpan) {
        Swal.fire({
            icon: tipo === 'success' ? 'success' : 'error',
            text: texto,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            customClass: {
                popup: 'swal-toast-small'
            }
        });
        return;
    }
    
    if (tipo === 'success') {
        mensaje.style.backgroundColor = '#d4edda';
        mensaje.style.color = '#155724';
        textoSpan.innerHTML = '<i class="fas fa-check-circle"></i> ' + texto;
    } else {
        mensaje.style.backgroundColor = '#f8d7da';
        mensaje.style.color = '#721c24';
        textoSpan.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + texto;
    }
    
    mensaje.style.display = 'block';
    mensaje.style.opacity = '1';
    
    setTimeout(() => {
        mensaje.style.opacity = '0';
        setTimeout(() => {
            mensaje.style.display = 'none';
        }, 500);
    }, 3000);
}