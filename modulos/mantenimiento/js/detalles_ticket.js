// js/detalles_ticket.js

let ticketGlobal = null;
let fotosActuales = [];
let fotoActualIndex = 0;
let nuevasFotos = [];
let materialesFrecuentes = [];
let materialesTicket = [];
let colaboradoresTicket = [];

function initDetallesTicket(data) {
    ticketGlobal = data;
    fotosActuales = data.fotos || [];
    
    // Inicializar selector de urgencia
    if (data.puedeEditar) {
        initSelectorUrgencia(data.nivelUrgencia, data.coloresUrgencia, data.textosUrgencia);
    }
    
    // Inicializar carousel de fotos
    initFotosCarousel();
    
    // Cargar materiales (solo para mantenimiento)
    if (data.tipo_formulario === 'mantenimiento_general') {
        cargarMateriales();
    }
    
    // Cargar colaboradores
    cargarColaboradores();
}

// ==================== SELECTOR DE URGENCIA ====================
function initSelectorUrgencia(nivelActual, colores, textos) {
    const container = document.getElementById('urgencia-selector');
    const nivelSeleccionado = nivelActual || 0;
    const colorActual = colores[nivelSeleccionado];
    const textoActual = textos[nivelSeleccionado];
    
    let html = `
        <div class="urgencia-selector-compacto" 
             style="background-color: ${colorActual};" 
             onclick="toggleOpcionesUrgencia()">
            <span id="urgencia-texto-actual">${textoActual}</span>
        </div>
        <div class="urgencia-opciones" id="urgencia-opciones" style="display: none;">
    `;
    
    [0, 1, 2, 3, 4].forEach(nivel => {
        const selected = nivel === nivelSeleccionado ? 'selected' : '';
        html += `
            <div class="urgencia-opcion ${selected}" 
                 style="background-color: ${colores[nivel]};"
                 onclick="seleccionarUrgencia(${nivel})">
                ${textos[nivel]}
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function toggleOpcionesUrgencia() {
    const opciones = document.getElementById('urgencia-opciones');
    opciones.style.display = opciones.style.display === 'none' ? 'block' : 'none';
}

function seleccionarUrgencia(nivel) {
    document.getElementById('nivel_urgencia_hidden').value = nivel;
    
    const textoActual = document.getElementById('urgencia-texto-actual');
    const selectorCompacto = textoActual.closest('.urgencia-selector-compacto');
    
    textoActual.textContent = ticketGlobal.textosUrgencia[nivel];
    selectorCompacto.style.backgroundColor = ticketGlobal.coloresUrgencia[nivel];
    
    // Actualizar selección visual
    document.querySelectorAll('.urgencia-opcion').forEach(opcion => {
        opcion.classList.remove('selected');
    });
    event.target.classList.add('selected');
    
    toggleOpcionesUrgencia();
}

// ==================== FOTOS CAROUSEL ====================
function initFotosCarousel() {
    const container = document.getElementById('fotosCarousel');
    
    if (fotosActuales.length === 0) {
        container.innerHTML = '<p class="text-muted"><i class="bi bi-info-circle"></i> No hay fotografías adjuntas</p>';
        if (ticketGlobal.puedeEditar) {
            container.innerHTML += getBotonesAgregarFotos();
        }
        return;
    }
    
    let html = '<div class="fotos-carousel">';
    html += '<div class="fotos-carousel-inner">';
    
    fotosActuales.forEach((foto, index) => {
        const activeClass = index === 0 ? 'active' : '';
        html += `
            <div class="fotos-carousel-item ${activeClass}">
                <img src="uploads/tickets/${foto.foto}" alt="Foto ${index + 1}">
            </div>
        `;
    });
    
    html += '</div>';
    
    if (fotosActuales.length > 1) {
        html += `
            <button class="fotos-carousel-control prev" onclick="cambiarFoto(-1)">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="fotos-carousel-control next" onclick="cambiarFoto(1)">
                <i class="bi bi-chevron-right"></i>
            </button>
        `;
    }
    
    html += `<div class="fotos-indicators">Foto ${fotoActualIndex + 1} de ${fotosActuales.length}</div>`;
    html += '</div>';
    
    if (ticketGlobal.puedeEditar) {
        html += '<div class="foto-actions">';
        html += getBotonesAgregarFotos();
        html += `<button type="button" class="btn btn-danger btn-sm" onclick="eliminarFotoActual()">
                    <i class="bi bi-trash"></i> Eliminar Foto Actual
                 </button>`;
        html += '</div>';
    }
    
    container.innerHTML = html;
}

function getBotonesAgregarFotos() {
    return `
        <button type="button" class="btn btn-primary-custom btn-sm" onclick="document.getElementById('inputFotos').click()">
            <i class="bi bi-upload"></i> Subir Fotos
        </button>
        <button type="button" class="btn btn-success btn-sm" onclick="tomarFoto()">
            <i class="bi bi-camera"></i> Tomar Foto
        </button>
        <input type="file" id="inputFotos" accept="image/*" multiple style="display: none;" onchange="handleFileSelect(event)">
        <video id="videoCamera" style="display: none; max-width: 300px; border-radius: 8px; margin-top: 10px;" autoplay></video>
        <canvas id="canvasCamera" style="display: none;"></canvas>
    `;
}

function cambiarFoto(direccion) {
    fotoActualIndex += direccion;
    if (fotoActualIndex < 0) fotoActualIndex = fotosActuales.length - 1;
    if (fotoActualIndex >= fotosActuales.length) fotoActualIndex = 0;
    
    document.querySelectorAll('.fotos-carousel-item').forEach((item, index) => {
        item.classList.toggle('active', index === fotoActualIndex);
    });
    
    document.querySelector('.fotos-indicators').textContent = 
        `Foto ${fotoActualIndex + 1} de ${fotosActuales.length}`;
}

function eliminarFotoActual() {
    if (!confirm('¿Eliminar esta foto?')) return;
    
    const fotoId = fotosActuales[fotoActualIndex].id;
    
    $.ajax({
        url: 'ajax/detalles_delete_foto.php',
        method: 'POST',
        data: { foto_id: fotoId, ticket_id: ticketGlobal.id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                fotosActuales.splice(fotoActualIndex, 1);
                fotoActualIndex = 0;
                initFotosCarousel();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function handleFileSelect(event) {
    Array.from(event.target.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
            nuevasFotos.push({ tipo: 'file', data: e.target.result, file: file });
        };
        reader.readAsDataURL(file);
    });
}

let stream = null;
function tomarFoto() {
    const video = document.getElementById('videoCamera');
    
    if (stream) {
        // Capturar foto
        const canvas = document.getElementById('canvasCamera');
        const context = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0);
        
        const dataURL = canvas.toDataURL('image/jpeg');
        nuevasFotos.push({ tipo: 'camera', data: dataURL });
        
        // Detener cámara
        stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.style.display = 'none';
    } else {
        // Iniciar cámara
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function(mediaStream) {
                stream = mediaStream;
                video.srcObject = stream;
                video.style.display = 'block';
            })
            .catch(function(err) {
                alert('Error al acceder a la cámara: ' + err.message);
            });
    }
}

// ==================== MATERIALES ====================
function cargarMateriales() {
    // Cargar materiales frecuentes
    $.ajax({
        url: 'ajax/detalles_get_materiales_frecuentes.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                materialesFrecuentes = response.materiales;
                cargarMaterialesTicket();
            }
        }
    });
}

function cargarMaterialesTicket() {
    $.ajax({
        url: 'ajax/detalles_get_materiales_ticket.php',
        method: 'GET',
        data: { ticket_id: ticketGlobal.id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                materialesTicket = response.materiales;
                renderizarMateriales();
            }
        }
    });
}

function renderizarMateriales() {
    const container = document.getElementById('materialesContainer');
    
    let html = `
        <div class="material-search">
            <input type="text" class="form-control form-control-sm" 
                   placeholder="Buscar material..." 
                   onkeyup="filtrarMateriales(this.value)">
        </div>
        <div class="materiales-lista" id="materialesLista">
    `;
    
    // Materiales asignados
    materialesTicket.forEach((mat, index) => {
        html += renderizarFilaMaterial(mat, index);
    });
    
    // Fila nueva para agregar
    html += renderizarFilaMaterial(null, -1);
    
    html += `
        </div>
        <div class="mt-2">
            <label class="form-label"><small>Otros materiales:</small></label>
            <textarea class="form-control form-control-sm" 
                      id="materialesOtros" 
                      rows="2" 
                      placeholder="Materiales no listados..."></textarea>
        </div>
    `;
    
    container.innerHTML = html;
}

function renderizarFilaMaterial(mat, index) {
    const materialId = mat ? mat.id : '';
    const materialNombre = mat ? mat.material_nombre : '';
    const detalle = mat ? mat.detalle : '';
    const procedencia = mat ? mat.procedencia : '';
    
    const isNuevo = index === -1;
    
    let html = `<div class="material-row" data-index="${index}">`;
    
    // Select de material
    html += '<select class="form-select form-select-sm material-select" ' +
            'onchange="handleMaterialChange(this, ${index})">';
    html += '<option value="">Seleccionar...</option>';
    
    materialesFrecuentes.forEach(m => {
        const selected = mat && m.id == mat.material_id ? 'selected' : '';
        html += `<option value="${m.id}" ${selected}>${m.nombre}</option>`;
    });
    
    if (isNuevo) {
        html += '<option value="nuevo">+ Nuevo Material</option>';
    }
    
    html += '</select>';
    
    // Input de detalle
    html += `<input type="text" class="form-control form-control-sm" 
                    placeholder="Detalle..." 
                    value="${detalle}">`;
    
    // Select de procedencia
    html += '<select class="form-select form-select-sm">';
    html += '<option value="">Procedencia...</option>';
    const procedencias = ['Bodega Villa', 'Bodega Altamira', 'Compra Sinsa', 'Compra Ferreteria', 'Otros'];
    procedencias.forEach(p => {
        const selected = p === procedencia ? 'selected' : '';
        html += `<option value="${p}" ${selected}>${p}</option>`;
    });
    html += '</select>';
    
    // Botón eliminar (solo para existentes)
    if (!isNuevo) {
        html += `<button type="button" class="btn btn-danger btn-sm" 
                         onclick="eliminarMaterial(${index})">
                    <i class="bi bi-x"></i>
                 </button>`;
    } else {
        html += '<div></div>';
    }
    
    html += '</div>';
    return html;
}

function handleMaterialChange(select, index) {
    if (select.value === 'nuevo') {
        const nombre = prompt('Nombre del nuevo material:');
        if (nombre && nombre.trim()) {
            crearMaterialFrecuente(nombre.trim(), index);
        } else {
            select.selectedIndex = 0;
        }
    } else if (index === -1 && select.value) {
        // Agregar nuevo material al ticket
        const row = select.closest('.material-row');
        const detalle = row.querySelector('input[type="text"]').value;
        const procedencia = row.querySelectorAll('select')[1].value;
        
        agregarMaterialTicket(select.value, detalle, procedencia);
    }
}

function crearMaterialFrecuente(nombre, index) {
    $.ajax({
        url: 'ajax/detalles_crear_material_frecuente.php',
        method: 'POST',
        data: { nombre: nombre },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                materialesFrecuentes.push(response.material);
                renderizarMateriales();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function agregarMaterialTicket(materialId, detalle, procedencia) {
    $.ajax({
        url: 'ajax/detalles_agregar_material_ticket.php',
        method: 'POST',
        data: {
            ticket_id: ticketGlobal.id,
            material_id: materialId,
            detalle: detalle,
            procedencia: procedencia
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                cargarMaterialesTicket();
            }
        }
    });
}

function eliminarMaterial(index) {
    if (!confirm('¿Eliminar este material?')) return;
    
    const materialId = materialesTicket[index].id;
    
    $.ajax({
        url: 'ajax/detalles_eliminar_material_ticket.php',
        method: 'POST',
        data: { material_id: materialId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                cargarMaterialesTicket();
            }
        }
    });
}

function filtrarMateriales(busqueda) {
    const filas = document.querySelectorAll('.material-row');
    const busq = busqueda.toLowerCase();
    
    filas.forEach(fila => {
        const select = fila.querySelector('.material-select');
        const texto = select.options[select.selectedIndex].text.toLowerCase();
        fila.style.display = texto.includes(busq) ? 'grid' : 'none';
    });
}

// ==================== COLABORADORES ====================
function cargarColaboradores() {
    $.ajax({
        url: 'ajax/agenda_get_colaboradores.php',
        method: 'GET',
        data: { ticket_id: ticketGlobal.id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                colaboradoresTicket = response.colaboradores;
                renderizarColaboradores();
            }
        }
    });
}

function renderizarColaboradores() {
    const container = document.getElementById('colaboradoresContainer');
    
    // Cargar operarios
    $.ajax({
        url: 'ajax/agenda_get_operarios.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '';
                
                // Colaboradores existentes
                colaboradoresTicket.forEach((col, index) => {
                    html += renderizarFilaColaborador(col, response.operarios, index);
                });
                
                // Fila nueva
                html += renderizarFilaColaborador(null, response.operarios, -1);
                
                container.innerHTML = html;
            }
        }
    });
}

function renderizarFilaColaborador(col, operarios, index) {
    const isNuevo = index === -1;
    
    let html = `<div class="colaborador-row" data-index="${index}">`;
    
    // Select tipo usuario
    html += '<select class="form-select form-select-sm tipo-usuario-select" ' +
            `onchange="handleTipoUsuarioChange(this, ${index})">`;
    html += '<option value="">Tipo de usuario...</option>';
    const tipos = ['Jefe de Manteniento', 'Lider de Infraestructura', 'Conductor', 'Auxiliar de Mantenimiento'];
    tipos.forEach(tipo => {
        const selected = col && col.tipo_usuario === tipo ? 'selected' : '';
        html += `<option value="${tipo}" ${selected}>${tipo}</option>`;
    });
    html += '</select>';
    
    // Select colaborador
    html += '<select class="form-select form-select-sm">';
    html += '<option value="">Seleccionar colaborador...</option>';
    operarios.forEach(op => {
        const selected = col && col.cod_operario == op.CodOperario ? 'selected' : '';
        html += `<option value="${op.CodOperario}" ${selected}>${op.nombre_completo}</option>`;
    });
    html += '</select>';
    
    // Botón eliminar (solo para existentes)
    if (!isNuevo) {
        html += `<button type="button" class="btn btn-danger btn-sm" 
                         onclick="eliminarColaborador(${index})">
                    <i class="bi bi-x"></i>
                 </button>`;
    } else {
        html += '<div></div>';
    }
    
    html += '</div>';
    return html;
}

function handleTipoUsuarioChange(select, index) {
    if (index === -1 && select.value) {
        // Crear nuevo colaborador
        $.ajax({
            url: 'ajax/agenda_save_colaborador.php',
            method: 'POST',
            data: {
                ticket_id: ticketGlobal.id,
                tipo_usuario: select.value,
                cod_operario: null
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    cargarColaboradores();
                }
            }
        });
    }
}

function eliminarColaborador(index) {
    if (!confirm('¿Eliminar este colaborador?')) return;
    
    const colaboradorId = colaboradoresTicket[index].id;
    
    $.ajax({
        url: 'ajax/agenda_delete_colaborador.php',
        method: 'POST',
        data: { colaborador_id: colaboradorId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                cargarColaboradores();
            }
        }
    });
}

// ==================== GUARDAR CAMBIOS ====================
function guardarCambios() {
    const formData = new FormData();
    formData.append('id', ticketGlobal.id);
    formData.append('titulo', document.getElementById('titulo').value);
    formData.append('area_equipo', document.getElementById('area_equipo').value);
    formData.append('descripcion', document.getElementById('descripcion').value);
    formData.append('nivel_urgencia', document.getElementById('nivel_urgencia_hidden').value);
    
    // Agregar nuevas fotos
    nuevasFotos.forEach((foto, index) => {
        if (foto.tipo === 'file') {
            formData.append('nuevas_fotos[]', foto.file);
        } else {
            formData.append(`foto_camera_${index}`, foto.data);
        }
    });
    
    // Guardar colaboradores actualizados
    const colabsActualizados = [];
    document.querySelectorAll('.colaborador-row').forEach(row => {
        const selects = row.querySelectorAll('select');
        if (selects[0].value && selects[1].value) {
            colabsActualizados.push({
                tipo_usuario: selects[0].value,
                cod_operario: selects[1].value
            });
        }
    });
    formData.append('colaboradores', JSON.stringify(colabsActualizados));
    
    // Guardar otros materiales
    const materialesOtros = document.getElementById('materialesOtros');
    if (materialesOtros) {
        formData.append('materiales_otros', materialesOtros.value);
    }
    
    $.ajax({
        url: 'ajax/detalles_actualizar_ticket.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const modal = document.querySelector('.modal.show');
                if (modal) {
                    bootstrap.Modal.getInstance(modal).hide();
                }
                
                if (typeof cargarDatos === 'function') {
                    cargarDatos();
                } else {
                    location.reload();
                }
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}