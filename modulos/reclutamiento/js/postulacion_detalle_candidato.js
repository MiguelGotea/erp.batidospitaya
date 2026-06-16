// postulacion_detalle_candidato.js

document.addEventListener('DOMContentLoaded', function () {
    cargarEntrevistadores();
    cargarSucursalesEntrevista();
});

async function cargarSucursalesEntrevista() {
    try {
        const response = await fetch('ajax/postulacion_requisicion_get_sucursales.php');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('selectSucursalesEntrevista');
            const selectedValue = select.getAttribute('data-selected');

            data.datos.forEach(sucursal => {
                const option = document.createElement('option');
                option.value = sucursal.codigo;
                option.textContent = sucursal.nombre;
                if (selectedValue == sucursal.codigo) option.selected = true;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar sucursales:', error);
    }
}

async function cargarEntrevistadores() {
    try {
        const response = await fetch('ajax/postulacion_detalle_candidato_get_entrevistadores.php');
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('entrevistadorRRHH');
            const selectedValue = select.getAttribute('data-selected');

            data.datos.forEach(entrevistador => {
                const option = document.createElement('option');
                option.value = entrevistador.CodOperario;
                option.textContent = `${entrevistador.nombre_completo} - ${entrevistador.cargo}`;
                if (selectedValue == entrevistador.CodOperario) option.selected = true;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error al cargar entrevistadores:', error);
    }
}

// ... (aprobarYProgramar function remains same) ...

async function modificarEntrevista() {
    const formEntrevista = document.getElementById('formEntrevista');
    const formTelefonica = document.getElementById('formEntrevistaTelefonica');

    if (!formTelefonica.checkValidity() || !formEntrevista.checkValidity()) {
        formTelefonica.reportValidity();
        formEntrevista.reportValidity();
        return;
    }

    const formDataTelefonica = new FormData(formTelefonica);
    const entrevistaTelefonica = Object.fromEntries(formDataTelefonica.entries());

    const result = await Swal.fire({
        title: '¿Guardar cambios?',
        text: 'Se actualizarán los datos y se reenviará la invitación.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, guardar'
    });

    if (!result.isConfirmed) return;

    try {
        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const response = await fetch('ajax/postulacion_detalle_candidato_modificar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_candidato: idCandidato,
                fecha_entrevista: document.getElementById('fechaEntrevista').value,
                hora_entrevista: document.getElementById('horaEntrevista').value,
                entrevistador_rrhh: document.getElementById('entrevistadorRRHH').value,
                modalidad: document.getElementById('modalidadEntrevista').value,
                notas: document.getElementById('notasAdicionales').value,
                entrevista_telefonica: entrevistaTelefonica
            })
        });

        const data = await response.json();
        if (data.success) {
            await Swal.fire('Éxito', 'Entrevista actualizada correctamente', 'success');
            window.location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    }
}

async function cancelarEntrevista() {
    const result = await Swal.fire({
        title: '¿Cancelar entrevista?',
        text: 'El candidato volverá a estado "Solicitado" y la entrevista será eliminada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, cancelar'
    });

    if (!result.isConfirmed) return;

    try {
        Swal.fire({ title: 'Cancelando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const response = await fetch('ajax/postulacion_detalle_candidato_cancelar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_candidato: idCandidato })
        });

        const data = await response.json();
        if (data.success) {
            await Swal.fire('Cancelada', data.message, 'success');
            window.location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    }
}

async function aprobarYProgramar() {
    const formEntrevista = document.getElementById('formEntrevista');
    const formTelefonica = document.getElementById('formEntrevistaTelefonica');

    if (!formTelefonica.checkValidity()) {
        formTelefonica.reportValidity();
        Swal.fire('Atención', 'Debe completar la sección de Entrevista Técnica Telefónica antes de proceder', 'warning');
        return;
    }

    if (!formEntrevista.checkValidity()) {
        formEntrevista.reportValidity();
        return;
    }

    const formDataTelefonica = new FormData(formTelefonica);
    const entrevistaTelefonica = Object.fromEntries(formDataTelefonica.entries());

    const fechaEntrevista = document.getElementById('fechaEntrevista').value;
    const horaEntrevista = document.getElementById('horaEntrevista').value;
    const entrevistadorRRHH = document.getElementById('entrevistadorRRHH').value;
    const modalidad = document.getElementById('modalidadEntrevista').value;
    const notas = document.getElementById('notasAdicionales').value;

    const result = await Swal.fire({
        title: '¿Aprobar y Programar?',
        html: `
            <div class="text-start">
                <p>Se enviará invitación de calendario con:</p>
                <ul>
                    <li><strong>Fecha:</strong> ${formatearFechaLegible(fechaEntrevista)}</li>
                    <li><strong>Hora:</strong> ${horaEntrevista}</li>
                    <li><strong>Modalidad:</strong> ${modalidad}</li>
                </ul>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, aprobar',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Procesando...',
            text: 'Aprobando candidato y enviando invitaciones',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('ajax/postulacion_detalle_candidato_aprobar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_candidato: idCandidato,
                fecha_entrevista: fechaEntrevista,
                hora_entrevista: horaEntrevista,
                entrevistador_rrhh: entrevistadorRRHH,
                modalidad: modalidad,
                notas: notas,
                entrevista_telefonica: entrevistaTelefonica
            })
        });

        const data = await response.json();

        if (data.success) {
            // Si data.email_status no viene, asumimos éxito si la operación general fue exitosa,
            // pero para ser más precisos, mostraremos el mensaje correcto basado en la respuesta fija del backend.
            if (data.email_status !== false) {
                await Swal.fire({
                    title: 'Aprobado',
                    text: 'Candidato aprobado y entrevista programada con invitación de calendario enviada.',
                    icon: 'success'
                });
            } else {
                await Swal.fire({
                    title: 'Aprobado con observaciones',
                    html: `
                        <p>El candidato ha sido aprobado, pero <strong>no se pudo enviar la invitación de calendario</strong>.</p>
                        <div class="alert alert-warning small text-start">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.email_error || 'Verifique sus credenciales de correo en su perfil de colaborador.'}
                        </div>
                    `,
                    icon: 'warning'
                });
            }
            // Redirigir al calendario
            window.location.href = 'postulacion_calendario.php';
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message, 'error');
    }
}

async function rechazarCandidato() {
    const formTelefonica = document.getElementById('formEntrevistaTelefonica');

    if (!formTelefonica.checkValidity()) {
        formTelefonica.reportValidity();
        Swal.fire('Atención', 'Debe completar la sección de Entrevista Técnica Telefónica antes de proceder', 'warning');
        return;
    }

    const formDataTelefonica = new FormData(formTelefonica);
    const entrevistaTelefonica = Object.fromEntries(formDataTelefonica.entries());

    const { value: motivo } = await Swal.fire({
        title: 'Rechazar Candidato',
        input: 'textarea',
        inputLabel: 'Motivo del rechazo (opcional)',
        inputPlaceholder: 'Indique el motivo del rechazo...',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Rechazar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (value && value.length < 10) {
                return 'El motivo debe tener al menos 10 caracteres';
            }
        }
    });

    if (motivo === undefined) return; // Cancelado

    try {
        Swal.fire({
            title: 'Procesando...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch('ajax/postulacion_detalle_candidato_rechazar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_candidato: idCandidato,
                motivo: motivo || '',
                entrevista_telefonica: entrevistaTelefonica
            })
        });

        const data = await response.json();

        if (data.success) {
            await Swal.fire('Rechazado', 'El candidato ha sido rechazado', 'success');
            window.location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message, 'error');
    }
}

async function guardarYSalir() {
    const formTelefonica = document.getElementById('formEntrevistaTelefonica');

    // Aquí no validamos estrictamente todo, solo que si hay datos sean coherentes si el navegador lo soporta
    // Pero permitimos guardar incluso si faltan campos requeridos (que se pedirán al Aprobar)
    // El usuario dijo "poder en una siguiente visita elegir una de las dos"

    const formDataTelefonica = new FormData(formTelefonica);
    const entrevistaTelefonica = Object.fromEntries(formDataTelefonica.entries());

    try {
        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const response = await fetch('ajax/postulacion_detalle_candidato_guardar_parcial.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_candidato: idCandidato,
                fecha_entrevista: document.getElementById('fechaEntrevista').value,
                hora_entrevista: document.getElementById('horaEntrevista').value,
                entrevistador_rrhh: document.getElementById('entrevistadorRRHH').value,
                modalidad: document.getElementById('modalidadEntrevista').value,
                notas: document.getElementById('notasAdicionales').value,
                entrevista_telefonica: entrevistaTelefonica
            })
        });

        const data = await response.json();
        if (data.success) {
            await Swal.fire('Guardado', 'Los datos se han guardado correctamente. Puedes continuar después.', 'success');
            history.back();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        Swal.fire('Error', error.message, 'error');
    }
}

function formatearFechaLegible(fecha) {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const d = new Date(fecha + 'T00:00:00');
    return `${d.getDate()} de ${meses[d.getMonth()]} de ${d.getFullYear()}`;
}

let modalCambiarPlazaInstance = null;

function mostrarModalCambiarPlaza() {
    const select = document.getElementById('selectPlazasActivas');
    select.innerHTML = '<option value="">Cargando plazas...</option>';
    
    // Cargar las plazas activas
    fetch('ajax/postulacion_get_plazas_activas.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                select.innerHTML = '<option value="">Seleccione plaza de destino...</option>';
                data.datos.forEach(plaza => {
                    const opt = document.createElement('option');
                    opt.value = plaza.id;
                    opt.textContent = `${plaza.nombre_cargo} - ${plaza.sucursal_nombre || 'Sin sucursal asignada'}`;
                    select.appendChild(opt);
                });
            } else {
                select.innerHTML = '<option value="">Error al cargar plazas</option>';
            }
        })
        .catch(err => {
            console.error(err);
            select.innerHTML = '<option value="">Error al cargar plazas</option>';
        });
        
    const modalEl = document.getElementById('modalCambiarPlaza');
    modalCambiarPlazaInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modalCambiarPlazaInstance.show();
}

async function cambiarPlazaCandidato(event) {
    event.preventDefault();
    const select = document.getElementById('selectPlazasActivas');
    const idPlaza = select.value;
    if (!idPlaza) {
        Swal.fire('Atención', 'Debe seleccionar una plaza de destino', 'warning');
        return;
    }
    
    const result = await Swal.fire({
        title: '¿Confirmar cambio de plaza?',
        text: 'Se modificará el cargo y sucursal a los que aplica este candidato.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar'
    });
    
    if (!result.isConfirmed) return;
    
    try {
        Swal.fire({
            title: 'Procesando...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        const response = await fetch('ajax/postulacion_cambiar_plaza.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_candidato: idCandidato,
                id_plaza: idPlaza
            })
        });
        
        const data = await response.json();
        if (data.success) {
            if (modalCambiarPlazaInstance) modalCambiarPlazaInstance.hide();
            
            // Remover backdrop huérfano si existiera
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';

            await Swal.fire('Éxito', data.message, 'success');
            window.location.reload();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al cambiar de plaza:', error);
        Swal.fire('Error', error.message, 'error');
    }
}