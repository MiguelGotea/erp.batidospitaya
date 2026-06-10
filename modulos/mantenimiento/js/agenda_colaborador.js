/* js/agenda_colaborador.js */

let activeStream     = null;
let activeVideoTrack = null;
let torchActivo      = false;
let currentCameraTarget = null;
let focusToastTimers = {};
let fotosEvidenciaTmp = []; // Almacena fotos (file o base64) para la tarea actual

function modalApertura() {
    const modal = new bootstrap.Modal(document.getElementById('aperturaModal'));
    modal.show();
}

async function guardarApertura() {
    // Cerrar modal de confirmación
    const modalEl = document.getElementById('aperturaModal');
    const modalInst = bootstrap.Modal.getInstance(modalEl);
    if (modalInst) modalInst.hide();

    Swal.fire({
        title: 'Iniciando reporte...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const response = await fetch('ajax/guardar_informe_apertura.php', {
            method: 'POST',
            body: new FormData()
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
 * Toggle visibilidad de la sección de kilometraje
 */
function toggleKilometraje(enabled) {
    const section = document.getElementById('kmSection');
    if (enabled) {
        section.classList.remove('d-none');
    } else {
        section.classList.add('d-none');
    }
}

/**
 * Abre modal para registrar KM Inicial
 */
function modalRegistrarKmInicial(informeId) {
    $('#km_inicial_informe_id').val(informeId);
    $('#formKmInicial')[0].reset();
    $('#preview_km_ini').addClass('d-none');
    $('#cam_km_ini_container').addClass('d-none');
    stopCamera();
    new bootstrap.Modal(document.getElementById('kmInicialModal')).show();
}

async function guardarKmInicial() {
    const form = document.getElementById('formKmInicial');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    if (!formData.get('km_foto_inicial').name && !formData.get('km_foto_inicial_cam')) {
        Swal.fire('Error', 'Debe adjuntar o tomar una foto del odómetro inicial', 'error');
        return;
    }

    Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });

    try {
        const response = await fetch('ajax/guardar_km_inicial.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('¡Guardado!', 'KM Inicial registrado correctamente', 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
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
 * GESTIÓN DE CÁMARA UNIVERSAL — Premium (con enfoque táctil + linterna)
 */
async function startCamera(target) {
    // Detener cualquier cámara activa primero
    stopCamera();

    currentCameraTarget = target;
    torchActivo         = false;
    const container = document.getElementById(`${target}_container`);
    const video     = document.getElementById(`${target}_video`);
    const btnTorch  = document.getElementById(`${target}_torch`);

    const constraints = {
        audio: false,
        video: {
            facingMode: { ideal: 'environment' },
            width:      { ideal: 3840 },
            height:     { ideal: 2160 },
            focusMode:  { ideal: 'continuous' }
        }
    };

    try {
        activeStream     = await navigator.mediaDevices.getUserMedia(constraints);
        activeVideoTrack = activeStream.getVideoTracks()[0];
        video.srcObject  = activeStream;
        container.classList.remove('d-none');

        video.onloadedmetadata = () => initCameraControls(target);

        // Tap-to-focus
        container.addEventListener('click', (e) => onCameraViewportClick(e, target));

    } catch (e) {
        Swal.fire('Cámara', 'No se pudo acceder a la cámara: ' + e.message, 'warning');
    }
}

function initCameraControls(target) {
    if (!activeVideoTrack) return;
    const caps     = activeVideoTrack.getCapabilities ? activeVideoTrack.getCapabilities() : {};
    const btnTorch = document.getElementById(`${target}_torch`);

    // Mostrar / ocultar botón de linterna
    if (btnTorch) {
        btnTorch.style.display = caps.torch ? 'flex' : 'none';
        btnTorch.classList.remove('on');
    }

    // Modo de enfoque continuo
    if (caps.focusMode && caps.focusMode.includes('continuous')) {
        activeVideoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
    }

    showFocusToast(target, 'Toca para enfocar', 2000);
}

function onCameraViewportClick(e, target) {
    // Ignorar clicks en botones dentro del container
    if (e.target.closest('button')) return;

    const container = document.getElementById(`${target}_container`);
    const ring      = document.getElementById(`${target}_ring`);
    if (!ring || !container) return;

    const rect = container.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    ring.style.left = x + 'px';
    ring.style.top  = y + 'px';
    ring.classList.remove('focus-active', 'focus-locked');
    void ring.offsetWidth; // reflow
    ring.classList.add('focus-active');

    if (activeVideoTrack) {
        const xR = x / rect.width;
        const yR = y / rect.height;
        activeVideoTrack.applyConstraints({
            advanced: [{ pointsOfInterest: [{ x: xR, y: yR }], focusMode: 'single-shot' }]
        }).then(() => {
            ring.classList.add('focus-locked');
            showFocusToast(target, '✓ Enfocado', 1500);
            setTimeout(() => {
                activeVideoTrack && activeVideoTrack.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
            }, 2000);
        }).catch(() => {
            ring.classList.add('focus-locked');
            showFocusToast(target, 'Enfoque ajustado', 1200);
        });
    }

    setTimeout(() => ring.classList.remove('focus-active', 'focus-locked'), 2500);
}

function showFocusToast(target, msg, duration) {
    const toast = document.getElementById(`${target}_toast`);
    if (!toast) return;
    if (focusToastTimers[target]) clearTimeout(focusToastTimers[target]);
    toast.textContent = msg;
    toast.style.opacity = '1';
    focusToastTimers[target] = setTimeout(() => { toast.style.opacity = '0'; }, duration || 1500);
}

function toggleCameraTorch(target) {
    if (!activeVideoTrack || currentCameraTarget !== target) return;
    torchActivo = !torchActivo;
    activeVideoTrack.applyConstraints({ advanced: [{ torch: torchActivo }] })
        .then(() => {
            const btn = document.getElementById(`${target}_torch`);
            if (btn) btn.classList.toggle('on', torchActivo);
        })
        .catch(() => {
            torchActivo = false;
            Swal.fire('Linterna', 'Este dispositivo no soporta linterna.', 'info');
        });
}

function captureSnapshot(target) {
    const video     = document.getElementById(`${target}_video`);
    const container = document.getElementById(`${target}_container`);
    const canvas    = document.createElement('canvas');

    // Resolución nativa para máxima calidad
    canvas.width  = video.videoWidth  || video.clientWidth;
    canvas.height = video.videoHeight || video.clientHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    // Flash visual
    if (container) {
        const flash = document.createElement('div');
        flash.style.cssText = 'position:absolute;inset:0;background:#fff;opacity:0.85;pointer-events:none;z-index:10;transition:opacity 0.35s';
        container.appendChild(flash);
        requestAnimationFrame(() => { flash.style.opacity = '0'; });
        setTimeout(() => flash.remove(), 400);
    }

    canvas.toBlob(blob => {
        const dataURL = canvas.toDataURL('image/jpeg', 0.92);

        if (target === 'cam_evidencia') {
            fotosEvidenciaTmp.push({ tipo: 'cam', data: dataURL });
            renderEvidenciaPreviews();
        } else {
            $(`#${target}_data`).val(dataURL);
            const preview = target.replace('cam_', 'preview_');
            $(`#${preview}`).removeClass('d-none').find('img').attr('src', dataURL);
        }

        stopCamera();
    }, 'image/jpeg', 0.92);
}

function stopCamera() {
    // Apagar linterna si estaba activa
    if (torchActivo && activeVideoTrack) {
        activeVideoTrack.applyConstraints({ advanced: [{ torch: false }] }).catch(() => {});
        torchActivo = false;
    }
    if (activeStream) {
        activeStream.getTracks().forEach(track => track.stop());
        activeStream     = null;
        activeVideoTrack = null;
    }
    if (currentCameraTarget) {
        const container = document.getElementById(`${currentCameraTarget}_container`);
        if (container) {
            // Clonar para eliminar listeners de tap-to-focus sin perder el DOM
            const clone = container.cloneNode(true);
            container.parentNode.replaceChild(clone, container);
            clone.classList.add('d-none');
        }
        // Resetear torch button
        const btnTorch = document.getElementById(`${currentCameraTarget}_torch`);
        if (btnTorch) { btnTorch.classList.remove('on'); btnTorch.style.display = 'none'; }
        currentCameraTarget = null;
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
    document.getElementById('cierre_informe_id').value = informeId;
    new bootstrap.Modal(document.getElementById('cierreModal')).show();
}

async function guardarCierre() {
    const informeId = document.getElementById('cierre_informe_id').value;
    if (!informeId) return;

    Swal.fire({ title: 'Finalizando informe...', didOpen: () => Swal.showLoading() });

    try {
        const formData = new FormData();
        formData.append('informe_id', informeId);

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
            window.open(`../compras/reembolsos_ia_nuevo.php?visita_id=${visitaId}`, '_blank');
        }
    });
}
