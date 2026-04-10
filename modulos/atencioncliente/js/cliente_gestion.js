// cliente_gestion.js

$(document).ready(function() {
    cargarDatosCliente();

    $('#formCliente').on('submit', function(e) {
        e.preventDefault();
        guardarCambios();
    });
});

function cargarDatosCliente() {
    if (!CONFIG.membresia) {
        Swal.fire('Error', 'No se proporcionó una membresía válida.', 'error');
        return;
    }

    $.ajax({
        url: 'ajax/cliente_get_perfil.php',
        method: 'POST',
        data: { membresia: CONFIG.membresia },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const d = response.datos;
                $('#id_clienteclub').val(d.id_clienteclub);
                $('#nombre').val(d.nombre);
                $('#apellido').val(d.apellido);
                $('#cedula').val(d.cedula);
                if (typeof formatearCedula === 'function') {
                    formatearCedula(document.getElementById('cedula'));
                }
                $('#celular').val(d.celular);
                $('#correo').val(d.correo);
                $('#fecha_nacimiento').val(d.fecha_nacimiento);
                $('#nombre_sucursal').val(d.nombre_sucursal || 'N/A');
                $('#fecha_registro').val(d.fecha_registro);
                $('#puntos_iniciales').val(d.puntos_iniciales || 0);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'No se pudo cargar la información del cliente.', 'error');
        }
    });
}

function guardarCambios() {
    // Clonar los datos para limpiar la cédula antes de enviar
    const $form = $('#formCliente');
    const originalCedula = $('#cedula').val();
    
    // Limpiar guiones de la cédula para guardar en BD
    $('#cedula').val(originalCedula.replace(/-/g, ''));
    
    const formData = $form.serialize();
    
    // Restaurar valor con guiones en el input
    $('#cedula').val(originalCedula);

    Swal.fire({
        title: '¿Guardar cambios?',
        text: 'Se actualizarán los datos del cliente.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/cliente_guardar.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('¡Éxito!', response.message, 'success').then(() => {
                            window.location.href = '?membresia=' + encodeURIComponent(CONFIG.membresia) + '&modo=view';
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Ocurrió un error al intentar guardar los cambios.', 'error');
                }
            });
        }
    });
}
