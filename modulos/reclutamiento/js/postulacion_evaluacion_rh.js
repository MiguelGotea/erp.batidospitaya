// postulacion_evaluacion_rh.js

document.addEventListener('DOMContentLoaded', function () {
    initStarRatings();
    initFileUpload();
    cargarJefeInmediato();
    calcularPuntaje();

    // Escuchar cambios en veredicto (si hubiera un select, pero aquí son botones)
    // En este diseño, el veredicto se pasa al hacer clic en finalizarEvaluacion
});

async function cargarJefeInmediato() {
    try {
        const idPostulacion = document.querySelector('input[name="id_postulacion"]').value;
        const response = await fetch(`ajax/postulacion_get_jefe_inmediato.php?id=${idPostulacion}`);
        const data = await response.json();

        const select = document.getElementById('entrevistadorJefe');
        const msg = document.getElementById('msgJefe');
        select.innerHTML = '';

        if (data.success) {
            data.datos.forEach(jefe => {
                const option = document.createElement('option');
                option.value = jefe.CodOperario;
                option.textContent = `${jefe.nombre_completo} - ${jefe.cargo_nombre}`;
                select.appendChild(option);
            });

            if (data.jefe_automatico) {
                msg.textContent = "Jefe inmediato identificado automáticamente.";
                msg.className = "text-success small";
            } else {
                msg.textContent = data.message;
                msg.className = "text-warning small";
            }
        } else {
            const option = document.createElement('option');
            option.value = "";
            option.textContent = "No se encontró jefe automático";
            select.appendChild(option);
            msg.textContent = data.message;
            msg.className = "text-danger small";
        }
    } catch (error) {
        console.error('Error al cargar jefe:', error);
    }
}

// ========================================
// ESTRELLAS (STAR RATING)
// ========================================
function initStarRatings() {
    const starContainers = document.querySelectorAll('.star-rating');

    starContainers.forEach(container => {
        const stars = container.querySelectorAll('.star');
        const hiddenInput = container.querySelector('input[type="hidden"]');

        stars.forEach(star => {
            star.addEventListener('click', function () {
                const val = parseInt(this.dataset.value);
                hiddenInput.value = val;
                updateStars(container, val);
                calcularPuntaje();
            });

            star.addEventListener('mouseover', function () {
                const val = parseInt(this.dataset.value);
                previewStars(container, val);
            });

            star.addEventListener('mouseout', function () {
                const currentVal = parseInt(hiddenInput.value);
                updateStars(container, currentVal);
            });
        });
    });
}

function updateStars(container, val) {
    const stars = container.querySelectorAll('.star');
    stars.forEach(star => {
        const starVal = parseInt(star.dataset.value);
        if (starVal <= val) {
            star.classList.remove('bi-star');
            star.classList.add('bi-star-fill', 'active');
        } else {
            star.classList.add('bi-star');
            star.classList.remove('bi-star-fill', 'active');
        }
    });
}

function previewStars(container, val) {
    const stars = container.querySelectorAll('.star');
    stars.forEach(star => {
        const starVal = parseInt(star.dataset.value);
        if (starVal <= val) {
            star.classList.add('bi-star-fill');
            star.classList.remove('bi-star');
        } else {
            star.classList.remove('bi-star-fill');
            star.classList.add('bi-star');
        }
    });
}

function calcularPuntaje() {
    const inputs = document.querySelectorAll('input[type="hidden"][name^="p"]');
    let total = 0;
    let count = 0;

    inputs.forEach(input => {
        const val = parseInt(input.value);
        if (val > 0) {
            total += val;
            count++;
        }
    });

    const promedio = count > 0 ? (total / count).toFixed(1) : "0.0";
    document.getElementById('puntajeDisplay').textContent = promedio;

    // Pintar estrellas de resumen
    const starsDisplay = document.getElementById('starsDisplay');
    starsDisplay.innerHTML = '';
    const roundPromedio = Math.round(parseFloat(promedio));
    for (let i = 1; i <= 5; i++) {
        const icon = i <= roundPromedio ? 'bi-star-fill' : 'bi-star';
        starsDisplay.innerHTML += `<i class="bi ${icon} mx-1"></i>`;
    }
}

// ========================================
// CARGA DE ARCHIVOS
// ========================================
function initFileUpload() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const removeFile = document.getElementById('removeFile');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        }
    });

    fileInput.addEventListener('change', handleFileSelect);

    removeFile.addEventListener('click', () => {
        fileInput.value = '';
        document.getElementById('fileInfo').classList.add('d-none');
        dropZone.classList.remove('d-none');
    });
}

function handleFileSelect() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        if (file.size > 10 * 1024 * 1024) {
            Swal.fire('Error', 'El archivo no debe pesar más de 10MB', 'error');
            fileInput.value = '';
            return;
        }
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileInfo').classList.remove('d-none');
        document.getElementById('dropZone').classList.add('d-none');
    }
}

// ========================================
// CÁMARA
// ========================================
let stream = null;

async function activarCamara() {
    const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
    modal.show();

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        document.getElementById('video').srcObject = stream;
    } catch (err) {
        console.error("Error al acceder a la cámara:", err);
        Swal.fire('Error', 'No se pudo acceder a la cámara', 'error');
    }
}

document.getElementById('cameraModal').addEventListener('hidden.bs.modal', function () {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
});

document.getElementById('snap').addEventListener('click', function () {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    canvas.toBlob(blob => {
        const file = new File([blob], "foto_entrevista.jpg", { type: "image/jpeg" });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('fileInput').files = dataTransfer.files;
        handleFileSelect();
        bootstrap.Modal.getInstance(document.getElementById('cameraModal')).hide();
    }, 'image/jpeg');
});

// ========================================
// FINALIZAR
// ========================================
async function finalizarEvaluacion(veredicto) {
    const form = document.getElementById('formEvalRH');

    // Validaciones basicas
    const inputs = document.querySelectorAll('input[type="hidden"][name^="p"]');
    let allValid = true;
    inputs.forEach(input => {
        if (parseInt(input.value) === 0) allValid = false;
    });

    if (!allValid) {
        Swal.fire('Validación', 'Por favor califica todos los ítems con al menos 1 estrella', 'warning');
        return;
    }

    const conclusiones = form.conclusiones.value.trim();
    if (conclusiones.length < 10) {
        Swal.fire('Validación', 'Por favor escribe una conclusión más detallada (mín. 10 caracteres)', 'warning');
        return;
    }

    const result = await Swal.fire({
        title: `¿Confirmar que desea ${veredicto === 'Aprobado' ? 'APROBAR' : 'RECHAZAR'} al candidato?`,
        text: `Esta acción registrará la evaluación de RH con veredicto: ${veredicto.toUpperCase()}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: veredicto === 'Aprobado' ? '#198754' : '#dc3545'
    });

    if (!result.isConfirmed) return;

    const formData = new FormData(form);

    // Validar campos de entrevista si es aprobar
    if (veredicto === 'Aprobado') {
        const fecha = formData.get('fecha_entrevista');
        const hora = formData.get('hora_entrevista');
        const jefe = formData.get('entrevistador_jefe');

        if (!fecha || !hora || !jefe) {
            Swal.fire('Validación', 'Debe completar los datos de la entrevista (Fecha, Hora y Jefe)', 'warning');
            return;
        }
    }

    formData.append('veredicto', veredicto);
    formData.append('puntaje_acumulado', document.getElementById('puntajeDisplay').textContent);

    try {
        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const response = await fetch('ajax/postulacion_evaluacion_rh_guardar.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            await Swal.fire('Éxito', 'Evaluación guardada correctamente', 'success');
            if (idPlaza > 0) {
                window.location.href = `postulacion_candidatos_plaza.php?id=${idPlaza}`;
            } else {
                window.location.href = 'postulacion_plazas_activas.php';
            }
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}
