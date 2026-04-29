// postulacion_evaluacion_jefe.js
// Similar a RH pero apunta a endpoint de Jefe

document.addEventListener('DOMContentLoaded', function () {
    initStarRatings();
    initFileUpload();
    calcularPuntaje();
});

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
                previewStars(container, parseInt(this.dataset.value));
            });
            star.addEventListener('mouseout', function () {
                updateStars(container, parseInt(hiddenInput.value));
            });
        });
    });
}

function updateStars(container, val) {
    container.querySelectorAll('.star').forEach(star => {
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
    container.querySelectorAll('.star').forEach(star => {
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
    let total = 0; let count = 0;
    inputs.forEach(input => {
        const val = parseInt(input.value);
        if (val > 0) { total += val; count++; }
    });
    const promedio = count > 0 ? (total / count).toFixed(1) : "0.0";
    document.getElementById('puntajeDisplay').textContent = promedio;
    const starsDisplay = document.getElementById('starsDisplay');
    starsDisplay.innerHTML = '';
    const roundPromedio = Math.round(parseFloat(promedio));
    for (let i = 1; i <= 5; i++) {
        starsDisplay.innerHTML += `<i class="bi ${i <= roundPromedio ? 'bi-star-fill' : 'bi-star'} mx-1"></i>`;
    }
}

function initFileUpload() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault(); dropZone.classList.remove('dragover');
        if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; handleFileSelect(); }
    });
    fileInput.addEventListener('change', handleFileSelect);
    document.getElementById('removeFile').addEventListener('click', () => {
        fileInput.value = ''; document.getElementById('fileInfo').classList.add('d-none');
        dropZone.classList.remove('d-none');
    });
}

function handleFileSelect() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput.files && fileInput.files[0]) {
        if (fileInput.files[0].size > 10 * 1024 * 1024) {
            Swal.fire('Error', 'Archivo demasiado grande (máx 10MB)', 'error');
            fileInput.value = ''; return;
        }
        document.getElementById('fileName').textContent = fileInput.files[0].name;
        document.getElementById('fileInfo').classList.remove('d-none');
        document.getElementById('dropZone').classList.add('d-none');
    }
}

let stream = null;
async function activarCamara() {
    const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
    modal.show();
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        document.getElementById('video').srcObject = stream;
    } catch (err) { Swal.fire('Error', 'No se pudo acceder a la cámara', 'error'); }
}

document.getElementById('cameraModal').addEventListener('hidden.bs.modal', function () {
    if (stream) stream.getTracks().forEach(track => track.stop());
});

document.getElementById('snap').addEventListener('click', function () {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        const file = new File([blob], "evidencia_tecnica.jpg", { type: "image/jpeg" });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('fileInput').files = dataTransfer.files;
        handleFileSelect();
        bootstrap.Modal.getInstance(document.getElementById('cameraModal')).hide();
    }, 'image/jpeg');
});

async function finalizarEvaluacion(veredicto) {
    const form = document.getElementById('formEvalJefe');
    const inputs = document.querySelectorAll('input[type="hidden"][name^="p"]');
    let allValid = true; inputs.forEach(input => { if (parseInt(input.value) === 0) allValid = false; });
    if (!allValid) { Swal.fire('Validación', 'Califique todos los puntos', 'warning'); return; }
    if (form.conclusiones.value.trim().length < 10) { Swal.fire('Validación', 'Escriba conclusiones más detalladas', 'warning'); return; }

    const result = await Swal.fire({
        title: `¿Confirmar que desea ${veredicto === 'Aprobado' ? 'APROBAR' : 'DESCARTAR'} al candidato?`,
        text: `Esta acción registrará la evaluación técnica con veredicto: ${veredicto.toUpperCase()}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: veredicto === 'Aprobado' ? '#198754' : '#dc3545'
    });
    if (!result.isConfirmed) return;

    const formData = new FormData(form);
    formData.append('veredicto', veredicto);
    formData.append('promedio_estrellas', document.getElementById('puntajeDisplay').textContent);

    try {
        Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const response = await fetch('ajax/postulacion_evaluacion_jefe_guardar.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            await Swal.fire('Éxito', 'Veredicto guardado correctamente', 'success');
            if (idPlaza > 0) {
                window.location.href = `postulacion_candidatos_plaza.php?id=${idPlaza}`;
            } else {
                window.location.href = 'postulacion_plazas_activas.php';
            }
        } else throw new Error(data.message);
    } catch (err) { Swal.fire('Error', err.message, 'error'); }
}
