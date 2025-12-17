// ============================================
// FUNCIONES JAVASCRIPT DEL SISTEMA DE MANTENIMIENTO
// ============================================

// Variables globales para cámara
let cameraStream = null;
let capturedImages = [];

// ============================================
// FUNCIONES DE FILTRADO DE TABLAS
// ============================================

function initTableFilters() {
    const filterIcons = document.querySelectorAll('.filter-icon');
    
    filterIcons.forEach(icon => {
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.nextElementSibling;
            
            // Cerrar otros dropdowns abiertos
            document.querySelectorAll('.filter-dropdown').forEach(d => {
                if (d !== dropdown) d.classList.remove('active');
            });
            
            dropdown.classList.toggle('active');
            
            // Llenar opciones si aún no se han llenado
            if (dropdown.dataset.filled !== 'true') {
                fillFilterOptions(dropdown, icon.dataset.column);
                dropdown.dataset.filled = 'true';
            }
        });
    });
    
    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', function() {
        document.querySelectorAll('.filter-dropdown').forEach(d => {
            d.classList.remove('active');
        });
    });
    
    // Prevenir que los clicks dentro del dropdown lo cierren
    document.querySelectorAll('.filter-dropdown').forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
}

function fillFilterOptions(dropdown, columnIndex) {
    const table = dropdown.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    const values = new Set();
    
    rows.forEach(row => {
        const cell = row.cells[columnIndex];
        if (cell) {
            const value = cell.textContent.trim();
            if (value) values.add(value);
        }
    });
    
    const optionsContainer = dropdown.querySelector('.filter-options');
    optionsContainer.innerHTML = '';
    
    Array.from(values).sort().forEach(value => {
        const option = document.createElement('div');
        option.className = 'filter-option';
        option.innerHTML = `
            <input type="checkbox" value="${value}" checked>
            <span>${value}</span>
        `;
        optionsContainer.appendChild(option);
        
        option.querySelector('input').addEventListener('change', function() {
            applyFilter(table, columnIndex);
        });
    });
}

function applyFilter(table, columnIndex) {
    const dropdown = table.querySelector(`[data-column="${columnIndex}"]`).nextElementSibling;
    const checkboxes = dropdown.querySelectorAll('.filter-option input[type="checkbox"]');
    const selectedValues = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);
    
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cell = row.cells[columnIndex];
        if (cell) {
            const value = cell.textContent.trim();
            row.style.display = selectedValues.includes(value) ? '' : 'none';
        }
    });
}

function sortTable(table, columnIndex, direction) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Intentar comparación numérica
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return direction === 'asc' ? aNum - bNum : bNum - aNum;
        }
        
        // Comparación alfabética
        return direction === 'asc' 
            ? aValue.localeCompare(bValue)
            : bValue.localeCompare(aValue);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

function clearFilter(dropdown, table, columnIndex) {
    const checkboxes = dropdown.querySelectorAll('.filter-option input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = true);
    applyFilter(table, columnIndex);
}

function searchFilterOptions(input) {
    const searchTerm = input.value.toLowerCase();
    const dropdown = input.closest('.filter-dropdown');
    const options = dropdown.querySelectorAll('.filter-option');
    
    options.forEach(option => {
        const text = option.textContent.toLowerCase();
        option.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// ============================================
// FUNCIONES DE CÁMARA Y SUBIDA DE ARCHIVOS
// ============================================

function initFileUpload(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const fileInput = container.querySelector('.file-input');
    const btnFile = container.querySelector('.btn-upload-file');
    const btnCamera = container.querySelector('.btn-upload-camera');
    
    if (btnFile && fileInput) {
        btnFile.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', function() {
            handleFileSelect(this.files, containerId);
        });
    }
    
    if (btnCamera) {
        btnCamera.addEventListener('click', () => openCamera(containerId));
    }
}

function handleFileSelect(files, containerId) {
    const container = document.getElementById(containerId);
    const previewContainer = container.querySelector('.preview-container');
    
    Array.from(files).forEach(file => {
        if (!file.type.startsWith('image/')) {
            alert('Solo se permiten archivos de imagen');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            addImagePreview(e.target.result, file, previewContainer);
        };
        reader.readAsDataURL(file);
    });
}

function addImagePreview(src, file, previewContainer) {
    const previewItem = document.createElement('div');
    previewItem.className = 'preview-item';
    previewItem.innerHTML = `
        <img src="${src}" alt="Preview">
        <button type="button" class="preview-remove" onclick="removePreview(this)">×</button>
    `;
    
    // Guardar referencia al archivo
    previewItem.dataset.file = src;
    capturedImages.push({src, file});
    
    previewContainer.appendChild(previewItem);
}

function removePreview(btn) {
    const item = btn.closest('.preview-item');
    const src = item.dataset.file;
    
    // Remover de array
    capturedImages = capturedImages.filter(img => img.src !== src);
    
    item.remove();
}

function openCamera(containerId) {
    const container = document.getElementById(containerId);
    let cameraContainer = container.querySelector('#camera-container');
    
    if (!cameraContainer) {
        cameraContainer = document.createElement('div');
        cameraContainer.id = 'camera-container';
        cameraContainer.innerHTML = `
            <video id="camera-video" autoplay></video>
            <canvas id="camera-canvas"></canvas>
            <div class="camera-controls">
                <button type="button" class="btn btn-primary" onclick="capturePhoto('${containerId}')">
                    📷 Capturar Foto
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeCamera('${containerId}')">
                    ✖ Cerrar Cámara
                </button>
            </div>
        `;
        container.appendChild(cameraContainer);
    }
    
    const video = cameraContainer.querySelector('#camera-video');
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            cameraStream = stream;
            video.srcObject = stream;
        })
        .catch(err => {
            alert('No se pudo acceder a la cámara: ' + err.message);
        });
}

function capturePhoto(containerId) {
    const container = document.getElementById(containerId);
    const video = container.querySelector('#camera-video');
    const canvas = container.querySelector('#camera-canvas');
    const previewContainer = container.querySelector('.preview-container');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    
    canvas.toBlob(blob => {
        const file = new File([blob], `foto_${Date.now()}.jpg`, { type: 'image/jpeg' });
        const src = canvas.toDataURL('image/jpeg');
        addImagePreview(src, file, previewContainer);
    }, 'image/jpeg', 0.8);
}

function closeCamera(containerId) {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    
    const container = document.getElementById(containerId);
    const cameraContainer = container.querySelector('#camera-container');
    if (cameraContainer) {
        cameraContainer.remove();
    }
}

// ============================================
// FUNCIONES DE MODALES
// ============================================

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        // Limpiar formularios dentro del modal
        const forms = modal.querySelectorAll('form');
        forms.forEach(form => form.reset());
        
        // Limpiar previews de imágenes
        const previews = modal.querySelectorAll('.preview-container');
        previews.forEach(preview => preview.innerHTML = '');
        capturedImages = [];
    }
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target.id);
    }
});

// ============================================
// FUNCIONES DE VALIDACIÓN
// ============================================

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            field.style.borderColor = '#ddd';
        }
    });
    
    if (!isValid) {
        alert('Por favor complete todos los campos obligatorios');
    }
    
    return isValid;
}

// ============================================
// FUNCIONES DE ALERTAS
// ============================================

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '10000';
    alertDiv.style.minWidth = '300px';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 4000);
}

function showLoading(show = true) {
    const loadingElements = document.querySelectorAll('.loading');
    loadingElements.forEach(el => {
        if (show) {
            el.classList.add('active');
        } else {
            el.classList.remove('active');
        }
    });
}

// ============================================
// FUNCIONES AJAX HELPERS
// ============================================

function ajaxRequest(url, data, callback, method = 'POST') {
    showLoading(true);
    
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        showLoading(false);
        callback(result);
    })
    .catch(error => {
        showLoading(false);
        console.error('Error:', error);
        showAlert('Error en la solicitud', 'danger');
    });
}

// ============================================
// UTILIDADES
// ============================================

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('es-NI', {
        style: 'currency',
        currency: 'NIO'
    }).format(amount);
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    initTableFilters();
});