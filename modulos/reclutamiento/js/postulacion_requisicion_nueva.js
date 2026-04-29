// postulacion_requisicion_nueva.js

document.addEventListener('DOMContentLoaded', async function () {
    // Primero cargamos los catálogos en orden
    await cargarAreas();
    await cargarCatalogosSucursales();
    await cargarCatalogosOperarios();

    // Si es edición, precargamos los datos
    if (typeof idRequisicionEdit !== 'undefined' && idRequisicionEdit > 0 && datosRequisicionEdit) {
        precargarDatosEdicion(datosRequisicionEdit);
    }
});

function precargarDatosEdicion(data) {
    document.getElementById('nombreCargo').value = data.nombre_cargo || '';
    document.getElementById('areaCargo').value = data.area_cargo || '';
    document.getElementById('sucursalSelect').value = data.sucursal || '';
    document.getElementById('cantidad').value = data.cantidad || 1;
    document.getElementById('salarioPropuesto').value = data.salario_propuesto || '';
    document.getElementById('nivelUrgencia').value = data.nivel_urgencia || '';
    document.getElementById('jefeDirectoSelect').value = data.cargo_reporta_a || '';
    document.getElementById('justificacion').value = data.justificacion || '';
    
    // Perfil
    document.getElementById('estudiosMinimos').value = data.estudios_minimos || '';
    document.getElementById('carrerasAptas').value = data.carreras_aptas || '';
    document.getElementById('conocimientosEspecificos').value = data.conocimientos_especificos || '';
    document.getElementById('idiomas').value = data.idiomas || '';
    document.getElementById('herramientasOffice').value = data.herramientas_office || '';
    document.getElementById('aptitudesEspecificas').value = data.aptitudes_especificas || '';
    document.getElementById('experienciaDeseada').value = data.experiencia_deseada || '';
    document.getElementById('funcionesResponsabilidades').value = data.funciones_responsabilidades || '';
}

// ========================================
// CARGAR ÁREAS DESDE BD
// ========================================
async function cargarAreas() {
    try {
        const response = await fetch('ajax/postulacion_requisicion_get_areas.php');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('areaCargo');
            data.datos.forEach(area => {
                const option = document.createElement('option');
                option.value = area;
                option.textContent = area;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar áreas:', error);
    }
}

// ========================================
// CARGAR CATÁLOGOS COMPLETOS
// ========================================
async function cargarCatalogosSucursales() {
    try {
        const response = await fetch('ajax/postulacion_requisicion_get_sucursales.php');
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('sucursalSelect');
            select.innerHTML = '<option value="">Seleccione sucursal...</option>';
            data.datos.forEach(suc => {
                const option = document.createElement('option');
                option.value = suc.codigo;
                option.textContent = suc.nombre;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar sucursales:', error);
        document.getElementById('sucursalSelect').innerHTML = '<option value="">Error al cargar</option>';
    }
}

async function cargarCatalogosOperarios() {
    try {
        const response = await fetch('ajax/postulacion_get_cargos_reporta.php');
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('jefeDirectoSelect');
            select.innerHTML = '<option value="">Seleccione cargo del jefe...</option>';
            data.datos.forEach(cargo => {
                const option = document.createElement('option');
                option.value = cargo.CodNivelesCargos;
                option.textContent = cargo.cargo_nombre;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar operarios:', error);
        document.getElementById('jefeDirectoSelect').innerHTML = '<option value="">Error al cargar</option>';
    }
}

// ========================================
// ENVIAR FORMULARIO
// ========================================
async function enviarSolicitud() {
    const form = document.getElementById('formRequisicion');

    // Validar nombre cargo
    const nombreCargo = document.getElementById('nombreCargo').value.trim();
    if (!nombreCargo || nombreCargo.length < 3) {
        Swal.fire('Validación', 'El nombre del cargo debe tener al menos 3 caracteres', 'warning');
        return;
    }

    // Validar sucursal
    const sucursalSeleccionada = document.getElementById('sucursalSelect').value;
    if (!sucursalSeleccionada) {
        Swal.fire('Validación', 'Debe seleccionar una sucursal de la lista', 'warning');
        return;
    }

    // Validar jefe directo
    const jefeSeleccionado = document.getElementById('jefeDirectoSelect').value;
    if (!jefeSeleccionado) {
        Swal.fire('Validación', 'Debe seleccionar un jefe directo de la lista', 'warning');
        return;
    }

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Validar justificación
    const justificacion = document.getElementById('justificacion').value;
    if (justificacion.length < 20) {
        Swal.fire('Validación', 'La justificación debe tener al menos 20 caracteres', 'warning');
        return;
    }

    const result = await Swal.fire({
        title: (typeof idRequisicionEdit !== 'undefined' && idRequisicionEdit > 0) ? '¿Guardar cambios?' : '¿Enviar solicitud?',
        text: (typeof idRequisicionEdit !== 'undefined' && idRequisicionEdit > 0) ? 'Se actualizarán los datos de la requisición' : 'La requisición será enviada a gerencia para su aprobación',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#218838',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    const formData = new FormData(form);
    // Añadimos manualmente si fuera necesario, pero FormData(form) debería capturar todo por el atributo 'name'

    try {
        Swal.fire({
            title: (typeof idRequisicionEdit !== 'undefined' && idRequisicionEdit > 0) ? 'Guardando cambios...' : 'Enviando solicitud...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('ajax/postulacion_requisicion_guardar.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            await Swal.fire('Éxito', 'Solicitud enviada correctamente', 'success');
            window.location.href = 'postulacion_requisicion.php';
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message, 'error');
    }
}