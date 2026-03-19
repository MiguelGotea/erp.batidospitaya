/* js/agenda_colaborador.js */

let activeStream = null;
let currentCameraTarget = null;
let fotosEvidenciaTmp = []; // Almacena fotos (file o base64) para la tarea actual

function modalApertura() {
    $('#formApertura')[0].reset();
    $('#preview_apertura').addClass('d-none');
    $('#cam_apertura_container').addClass('d-none');
    stopCamera();
    const modal = new bootstrap.Modal(document.getElementById('aperturaModal'));
    modal.show();
}

async function guardarApertura() {
    const form = document.getElementById('formApertura');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);

    // Validar que haya foto
    if (!formData.get('km_foto_inicial').name && !formData.get('km_foto_inicial_cam')) {
        Swal.fire('Error', 'Debe adjuntar o tomar una foto del kilometraje inicial', 'error');
        return;
    }

    Swal.fire({
        title: 'Iniciando reporte...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('ajax/guardar_informe_apertura.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();

        if (res.success) {
            Swal.fire('¡Éxito!', 'Informe iniciado correctamente', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Fallo en la conexión: ' + e.message, 'error');
    }
}

/**
 * Abre modal para registrar nueva parada (visita)
 */
function modalNuevaVisita(informeId) {
    $('#visita_informe_id').val(informeId);
    $('#formVisita')[0].reset();
    const modal = new bootstrap.Modal(document.getElementById('visitaModal'));
    modal.show();
}

async function guardarVisita() {
    const form = document.getElementById('formVisita');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);

    Swal.fire({ title: 'Registrando visita...', didOpen: () => Swal.showLoading() });

    try {
        const response = await fetch('ajax/guardar_visita.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Registrado', 'Visita guardada con éxito', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}

/**
 * ACTUALIZACIÓN INLINE DE VISITA
 */
async function actualizarVisitaInline(id, campo, valor) {
    let url = '';
    let data = new FormData();
    data.append('visita_id', id);

    if (campo === 'hora_llegada') {
        url = 'ajax/actualizar_hora_llegada.php';
        data.append('hora_llegada', valor);
    } else if (campo === 'hora_salida') {
        url = 'ajax/actualizar_hora_salida.php';
        data.append('hora_salida', valor);
    } else if (campo === 'materiales_stock') {
        url = 'ajax/actualizar_materiales.php';
        data.append('materiales_stock', valor);
    }

    if (!url) return;

    try {
        const response = await fetch(url, { method: 'POST', body: data });
        const res = await response.json();
        if (!res.success) {
            console.error('Error al actualizar:', res.message);
        }
    } catch (e) {
        console.error('Error de conexión:', e);
    }
}

/**
 * VALIDACIÓN DE IMPRESIÓN
 */
function validarImpresion(id, estado) {
    if (estado !== 'finalizado') {
        const inputs = document.querySelectorAll('input[onchange^="actualizarVisitaInline"]');
        const textareas = document.querySelectorAll('textarea[onchange^="actualizarVisitaInline"]');
        
        // Verificar si hay campos vacíos obligatorios (Término y Materiales)
        let incompleto = false;
        inputs.forEach(input => {
            if (input.onchange.toString().includes('hora_salida') && !input.value) incompleto = true;
        });
        textareas.forEach(textarea => {
            if (!textarea.value) incompleto = true;
        });

        if (incompleto) {
            Swal.fire({
                title: 'No se puede imprimir',
                text: 'Debe completar la Hora de Salida y los Materiales de todas las visitas antes de imprimir el reporte.',
                icon: 'warning',
                confirmButtonColor: '#0E544C'
            });
            return;
        }
    }
    
    window.open(`imprimir_informe.php?id=${id}`, '_blank');
}

/**
 * Abre modal para registrar tarea dentro de una visita
 */
function modalNuevaTarea(visitaId, codSucursal) {
    $('#tarea_visita_id').val(visitaId);
    $('#formTarea')[0].reset();

    // Limpiar select y mostrar cargando
    const select = $('#formTarea select[name="ticket_id"]');
    select.empty().append('<option value="">Cargando tickets de esta sucursal...</option>');

    $('#evidencia_previews').empty();
    fotosEvidenciaTmp = [];
    stopCamera();

    const modal = new bootstrap.Modal(document.getElementById('tareaModal'));
    modal.show();

    // Cargar tickets por sucursal vía AJAX
    $.ajax({
        url: 'ajax/get_tickets_sucursal.php',
        method: 'GET',
        data: { cod_sucursal: codSucursal },
        success: function (res) {
            select.empty().append('<option value="">Seleccionar Ticket...</option>');
            if (res.success) {
                if (res.tickets.length > 0) {
                    res.tickets.forEach(t => {
                        const descCorta = t.descripcion ? (t.descripcion.length > 50 ? t.descripcion.substring(0, 50) + '...' : t.descripcion) : '';
                        const displayText = t.titulo + (descCorta ? ' - ' + descCorta : '');
                        select.append(`<option value="${t.id}">${displayText}</option>`);
                    });
                } else {
                    select.append('<option value="" disabled>No hay tickets pendientes en esta sucursal</option>');
                }
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        },
        error: function () {
            select.empty().append('<option value="">Error al cargar tickets</option>');
        }
    });
}

/**
 * Procesa la selección de archivos múltiples de evidencia
 */
function previewEvidencia(input) {
    if (input.files) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function (e) {
                fotosEvidenciaTmp.push({ tipo: 'file', data: e.target.result, file: file });
                renderEvidenciaPreviews();
            };
            reader.readAsDataURL(file);
        });
    }
}

/**
 * Renderiza las miniaturas de evidencia en el modal
 */
function renderEvidenciaPreviews() {
    const container = $('#evidencia_previews');
    container.empty();
    fotosEvidenciaTmp.forEach((foto, index) => {
        container.append(`
            <div class="col-4 col-md-3 position-relative">
                <img src="${foto.data}" class="img-thumbnail rounded-3 w-100" style="height: 80px; object-fit: cover;">
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1 rounded-circle" 
                        onclick="removerFotoTmp(${index})"><i class="fas fa-times"></i></button>
            </div>
        `);
    });
}

function removerFotoTmp(index) {
    fotosEvidenciaTmp.splice(index, 1);
    renderEvidenciaPreviews();
}

/**
 * Guarda la tarea (con sus múltiples fotos)
 */
async function guardarTarea() {
    const form = document.getElementById('formTarea');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (fotosEvidenciaTmp.length === 0) {
        Swal.fire('Atención', 'Debe incluir al menos una foto de evidencia del trabajo', 'warning');
        return;
    }

    const formData = new FormData(form);

    // Consolidar fotos de cámara y archivos
    const fotosCam = [];
    const dt = new DataTransfer();

    fotosEvidenciaTmp.forEach(f => {
        if (f.tipo === 'file') {
            dt.items.add(f.file);
        } else {
            fotosCam.push(f.data);
        }
    });

    // Sobreescribir el input file con todos los archivos recolectados
    document.getElementById('evidencia_input').files = dt.files;
    formData.set('fotos_evidencia[]', dt.files); // Nota: fetch enviará el name del input
    // Para múltiples fotos via $_FILES[] usualmente se usa el name="fotos_evidencia[]"
    // Pero como estamos usando FormData manual:
    const finalFormData = new FormData(form);
    Array.from(dt.files).forEach(f => finalFormData.append('fotos_evidencia[]', f));
    finalFormData.append('fotos_camera_json', JSON.stringify(fotosCam));

    Swal.fire({ title: 'Guardando informe de tarea...', didOpen: () => Swal.showLoading() });


    try {
        const response = await fetch('ajax/guardar_tarea_informe.php', {
            method: 'POST',
            body: finalFormData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Guardado', 'Tarea registrada correctamente', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}

/**
 * GESTIÓN DE CÁMARA UNIVERSAL
 */
async function startCamera(target) {
    currentCameraTarget = target;
    const container = $(`#${target}_container`);
    const video = document.getElementById(`${target}_video`);

    try {
        activeStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        video.srcObject = activeStream;
        container.removeClass('d-none');
    } catch (e) {
        Swal.fire('Cámara', 'No se pudo acceder a la cámara: ' + e.message, 'warning');
    }
}

function captureSnapshot(target) {
    const video = document.getElementById(`${target}_video`);
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const dataURL = canvas.toDataURL('image/jpeg', 0.8);

    if (target === 'cam_evidencia') {
        fotosEvidenciaTmp.push({ tipo: 'cam', data: dataURL });
        renderEvidenciaPreviews();
    } else {
        $(`#${target}_data`).val(dataURL);
        const preview = target.replace('cam_', 'preview_');
        $(`#${preview}`).removeClass('d-none').find('img').attr('src', dataURL);
    }

    stopCamera();
}

function stopCamera() {
    if (activeStream) {
        activeStream.getTracks().forEach(track => track.stop());
        activeStream = null;
    }
    if (currentCameraTarget) {
        $(`#${currentCameraTarget}_container`).addClass('d-none');
    }
}

function previewFile(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            $(`#${previewId}`).removeClass('d-none').find('img').attr('src', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Zoom de imagen
 */
function zoomFoto(src) {
    $('#zoomImg').attr('src', src);
    new bootstrap.Modal(document.getElementById('zoomModal')).show();
}

/**
 * CIERRE DE JORNADA
 */
function modalCierre(informeId) {
    $('#cierre_informe_id').val(informeId);
    $('#formCierre')[0].reset();
    $('#preview_cierre').addClass('d-none');
    $('#cam_cierre_container').addClass('d-none');
    stopCamera();
    new bootstrap.Modal(document.getElementById('cierreModal')).show();
}

async function guardarCierre() {
    const form = document.getElementById('formCierre');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Validación de campos obligatorios en visitas (Salida y Materiales)
    const btnSalida = document.querySelector('button[onclick^="modalRegistrarSalida"]');
    const btnMat = document.querySelector('button[onclick^="modalRegistrarMateriales"]');
    
    if (btnSalida || btnMat) {
        Swal.fire({
            title: 'Informe Incompleto',
            text: 'Todas las visitas deben tener registrada la Hora de Salida y los Materiales Usados antes de finalizar.',
            icon: 'warning',
            confirmButtonColor: '#0E544C'
        });
        return;
    }

    const formData = new FormData(form);
    if (!formData.get('km_foto_final').name && !formData.get('km_foto_final_cam')) {
        Swal.fire('Error', 'Debe adjuntar foto del kilometraje final', 'error');
        return;
    }

    Swal.fire({ title: 'Finalizando informe...', didOpen: () => Swal.showLoading() });

    try {
        const response = await fetch('ajax/finalizar_informe_diario.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Informe Finalizado', 'El informe ha sido cerrado correctamente', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}

/**
 * NUEVA COMPRA / FACTURA
 */
function modalNuevaCompra(visitaId) {
    $('#compra_visita_id').val(visitaId);
    $('#formCompra')[0].reset();
    $('#preview_compra').addClass('d-none');
    $('#cam_compra_container').addClass('d-none');
    stopCamera();
    new bootstrap.Modal(document.getElementById('compraModal')).show();
}

async function guardarCompra() {
    const form = document.getElementById('formCompra');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    if (!formData.get('foto_factura').name && !formData.get('foto_factura_cam')) {
        Swal.fire('Error', 'Debe adjuntar la foto de la factura', 'error');
        return;
    }

    Swal.fire({ title: 'Subiendo factura...', didOpen: () => Swal.showLoading() });

    try {
        const response = await fetch('ajax/guardar_compra.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Éxito', 'Factura registrada', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}

/**
 * ELIMINACIÓN DE REGISTROS
 */
async function eliminarTarea(id) {
    const result = await Swal.fire({
        title: '¿Eliminar tarea?',
        text: 'Se borrará el registro y todas las fotos adjuntas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, borrar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        Swal.fire({ title: 'Eliminando...', didOpen: () => Swal.showLoading() });
        try {
            const formData = new FormData();
            formData.append('id', id);
            const response = await fetch('ajax/eliminar_tarea_informe.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();
            if (res.success) {
                Swal.fire('Borrado', 'La tarea ha sido eliminada', 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    }
}

async function eliminarCompra(id) {
    const result = await Swal.fire({
        title: '¿Eliminar factura?',
        text: 'Se borrará el registro y la foto de la factura.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, borrar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        Swal.fire({ title: 'Eliminando...', didOpen: () => Swal.showLoading() });
        try {
            const formData = new FormData();
            formData.append('id', id);
            const response = await fetch('ajax/eliminar_compra_informe.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();
            if (res.success) {
                Swal.fire('Borrado', 'La factura ha sido eliminada', 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    }
}

async function eliminarVisita(id) {
    const result = await Swal.fire({
        title: '¿Eliminar visita?',
        text: '¡ATENCIÓN! Esto borrará permanentemente la visita, todas sus tareas, compras y fotos asociadas.',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, borrar todo',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        Swal.fire({ title: 'Eliminando visita y registros...', didOpen: () => Swal.showLoading() });
        try {
            const formData = new FormData();
            formData.append('id', id);
            const response = await fetch('ajax/eliminar_visita_informe.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();
            if (res.success) {
                Swal.fire('Borrado', 'La visita y todo su contenido han sido eliminados', 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    }
}

/**
 * CAJA CHICA REGISTRO (OPERARIO)
 */
function modalValidarCaja(id, monto) {
    $('#caja_informe_id').val(id);
    $('#caja_monto').val(monto);
    $('#preview_caja').addClass('d-none');
    $('#cam_caja_container').addClass('d-none');
    stopCamera();
    new bootstrap.Modal(document.getElementById('validarCajaModal')).show();
}

async function guardarValidacionCaja() {
    const form = document.getElementById('formCaja');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);

    // Validar foto (archivo o cámara)
    if (!formData.get('foto_caja').name && !formData.get('foto_caja_cam')) {
        Swal.fire('Error', 'Debe adjuntar o tomar una foto del voucher', 'error');
        return;
    }

    Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });

    try {
        const response = await fetch('ajax/validar_caja_chica.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Éxito', 'Registro de caja chica guardado', 'success').then(() => location.reload());
            bootstrap.Modal.getInstance(document.getElementById('validarCajaModal')).hide();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}
function zoomFoto(src) {
    document.getElementById('zoomImg').src = src;
    new bootstrap.Modal(document.getElementById('zoomModal')).show();
}

/**
 * Redirige a la herramienta de reembolsos con el ID de la visita
 */
function generarReembolsoDesdeVisita(visitaId) {
    Swal.fire({
        title: '¿Generar reembolso?',
        text: "Se abrirá la herramienta de reembolsos con las facturas de esta visita pre-cargadas.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `../compras/reembolsos_ia_nuevo.php?visita_id=${visitaId}`;
        }
    });
}
