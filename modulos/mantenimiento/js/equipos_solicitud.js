// public_html/modulos/mantenimiento/js/equipos_solicitud.js

let archivosSeleccionados = [];

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('formSolicitud').addEventListener('submit', enviarSolicitud);
    document.getElementById('inputArchivos').addEventListener('change', manejarArchivos);
    document.getElementById('inputCamara').addEventListener('change', manejarArchivos);
});

// Abrir selector de archivos
function abrirArchivos() {
    document.getElementById('inputArchivos').click();
}

// Abrir cámara
function abrirCamara() {
    document.getElementById('inputCamara').click();
}

// Manejar archivos seleccionados
function manejarArchivos(event) {
    const files = Array.from(event.target.files);
    
    files.forEach(file => {
        // Validar tamaño (máximo 10MB por archivo)
        if (file.size > 10 * 1024 * 1024) {
            alert(`El archivo ${file.name} es demasiado grande. Máximo 10MB por archivo.`);
            return;
        }
        
        const archivoObj = {
            file: file,
            id: Date.now() + Math.random(),
            preview: null
        };
        
        // Generar preview para imágenes
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                archivoObj.preview = e.target.result;
                archivosSeleccionados.push(archivoObj);
                renderizarPrevisualizacion();
            };
            reader.readAsDataURL(file);
        } else {
            archivosSeleccionados.push(archivoObj);
            renderizarPrevisualizacion();
        }
    });
    
    // Limpiar input
    event.target.value = '';
}

// Renderizar previsualizacion
function renderizarPrevisualizacion() {
    const container = document.getElementById('previsualizacion');
    
    if (archivosSeleccionados.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    archivosSeleccionados.forEach(archivo => {
        html += `
            <div class="preview-item">
                ${archivo.preview ? 
                    `<img src="${archivo.preview}" alt="${archivo.file.name}">` :
                    `<div style="padding: 20px; text-align: center;">
                        📄<br>${archivo.file.name}
                    </div>`
                }
                <div style="font-size: 11px; margin-top: 5px; word-break: break-word;">
                    ${archivo.file.name}
                </div>
                <button type="button" class="btn-eliminar" onclick="eliminarArchivo('${archivo.id}')">
                    ×
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Eliminar archivo
function eliminarArchivo(id) {
    archivosSeleccionados = archivosSeleccionados.filter(a => a.id !== id);
    renderizarPrevisualizacion();
}

// Enviar solicitud
async function enviarSolicitud(event) {
    event.preventDefault();
    
    // Validar que haya al menos un archivo
    if (archivosSeleccionados.length === 0) {
        alert('Debe adjuntar al menos una foto o archivo como evidencia.');
        return;
    }
    
    const btnEnviar = document.getElementById('btnEnviar');
    btnEnviar.disabled = true;
    btnEnviar.innerHTML = '<span class="loading"></span> Enviando...';
    
    try {
        const formData = new FormData(document.getElementById('formSolicitud'));
        
        // Agregar archivos
        archivosSeleccionados.forEach((archivo, index) => {
            formData.append(`archivos[]`, archivo.file);
        });
        
        const response = await fetch('ajax/equipos_solicitud_guardar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Solicitud de mantenimiento enviada exitosamente');
            window.location.href = 'equipos_lista.php';
        } else {
            alert('Error: ' + data.message);
            btnEnviar.disabled = false;
            btnEnviar.innerHTML = 'Enviar Solicitud';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al enviar la solicitud. Por favor intente nuevamente.');
        btnEnviar.disabled = false;
        btnEnviar.innerHTML = 'Enviar Solicitud';
    }
}