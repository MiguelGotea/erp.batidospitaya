// public_html/modulos/mantenimiento/js/equipos_reporte.js

let archivosReporte = [];

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('formReporte').addEventListener('submit', guardarReporte);
    document.getElementById('inputArchivosReporte').addEventListener('change', manejarArchivosReporte);
    document.getElementById('inputCamaraReporte').addEventListener('change', manejarArchivosReporte);
});

// Agregar repuesto
function agregarRepuesto() {
    const template = document.getElementById('templateRepuesto');
    const clone = template.content.cloneNode(true);
    document.getElementById('listaRepuestos').appendChild(clone);
}

// Seleccionar repuesto
function seleccionarRepuesto(select) {
    const option = select.options[select.selectedIndex];
    const item = select.closest('.repuesto-item');
    
    if (option.value) {
        const costoBase = parseFloat(option.dataset.costo || 0);
        item.querySelector('.costo-base').value = costoBase.toFixed(2);
        item.querySelector('.costo-real').value = costoBase.toFixed(2);
        calcularTotal();
    } else {
        item.querySelector('.costo-base').value = '';
        item.querySelector('.costo-real').value = '';
        calcularTotal();
    }
}

// Eliminar repuesto
function eliminarRepuesto(btn) {
    btn.closest('.repuesto-item').remove();
    calcularTotal();
}

// Calcular total
function calcularTotal() {
    let totalRepuestos = 0;
    
    document.querySelectorAll('.repuesto-item').forEach(item => {
        const cantidad = parseFloat(item.querySelector('.cantidad-repuesto').value || 0);
        const costoReal = parseFloat(item.querySelector('.costo-real').value || 0);
        const total = cantidad * costoReal;
        
        item.querySelector('.total-repuesto').value = total.toFixed(2);
        totalRepuestos += total;
    });
    
    document.getElementById('totalRepuestos').textContent = totalRepuestos.toFixed(2);
    
    const costoManoObra = parseFloat(document.getElementById('costo_mano_obra').value || 0);
    const costoTotal = totalRepuestos + costoManoObra;
    
    document.getElementById('costo_total').value = costoTotal.toFixed(2);
}

// Archivos
function abrirArchivosReporte() {
    document.getElementById('inputArchivosReporte').click();
}

function abrirCamaraReporte() {
    document.getElementById('inputCamaraReporte').click();
}

function manejarArchivosReporte(event) {
    const files = Array.from(event.target.files);
    
    files.forEach(file => {
        if (file.size > 10 * 1024 * 1024) {
            alert(`El archivo ${file.name} es demasiado grande. Máximo 10MB.`);
            return;
        }
        
        const archivoObj = {
            file: file,
            id: Date.now() + Math.random(),
            preview: null
        };
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                archivoObj.preview = e.target.result;
                archivosReporte.push(archivoObj);
                renderizarPrevisualizacionReporte();
            };
            reader.readAsDataURL(file);
        } else {
            archivosReporte.push(archivoObj);
            renderizarPrevisualizacionReporte();
        }
    });
    
    event.target.value = '';
}

function renderizarPrevisualizacionReporte() {
    const container = document.getElementById('previsualizacionReporte');
    
    if (archivosReporte.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    archivosReporte.forEach(archivo => {
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
                <button type="button" class="btn-eliminar" onclick="eliminarArchivoReporte('${archivo.id}')">
                    ×
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function eliminarArchivoReporte(id) {
    archivosReporte = archivosReporte.filter(a => a.id !== id);
    renderizarPrevisualizacionReporte();
}

// Guardar reporte
async function guardarReporte(event) {
    event.preventDefault();
    
    // Validar que haya al menos un repuesto o que se confirme que no se usaron
    const repuestos = document.querySelectorAll('.repuesto-item');
    if (repuestos.length === 0) {
        if (!confirm('No ha agregado repuestos. ¿El mantenimiento no requirió repuestos?')) {
            return;
        }
    }
    
    const btnGuardar = document.getElementById('btnGuardarReporte');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<span class="loading"></span> Guardando...';
    
    try {
        const formData = new FormData(document.getElementById('formReporte'));
        
        // Agregar repuestos
        const repuestosData = [];
        document.querySelectorAll('.repuesto-item').forEach(item => {
            const repuestoId = item.querySelector('.select-repuesto').value;
            if (repuestoId) {
                repuestosData.push({
                    repuesto_id: repuestoId,
                    cantidad: item.querySelector('.cantidad-repuesto').value,
                    costo_unitario_real: item.querySelector('.costo-real').value,
                    observaciones: item.querySelector('.observaciones-repuesto').value
                });
            }
        });
        
        formData.append('repuestos', JSON.stringify(repuestosData));
        
        // Agregar archivos
        archivosReporte.forEach(archivo => {
            formData.append('archivos[]', archivo.file);
        });
        
        const response = await fetch('ajax/equipos_reporte_guardar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Reporte guardado exitosamente. El mantenimiento ha sido completado.');
            window.location.href = 'equipos_calendario.php';
        } else {
            alert('Error: ' + data.message);
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = 'Guardar Reporte';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al guardar el reporte. Por favor intente nuevamente.');
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Guardar Reporte';
    }
}