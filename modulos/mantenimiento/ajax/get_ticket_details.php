<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Ticket.php';

if (!isset($_GET['id'])) {
    die('ID de ticket requerido');
}

$ticket_model = new Ticket();
$ticket = $ticket_model->getById($_GET['id']);
$fotos = $ticket_model->getFotos($_GET['id']);
$colaboradores = $ticket_model->getColaboradores($_GET['id']);

if (!$ticket) {
    die('Ticket no encontrado');
}

// Determinar si puede editar (agregar lógica de permisos según necesites)
$puedeEditar = true; // Cambiar según tu lógica de permisos

$es_mantenimiento = ($ticket['tipo_formulario'] === 'mantenimiento_general');
$label_area = $es_mantenimiento ? 'Área' : 'Equipo';

// Obtener materiales si es mantenimiento
$materiales_ticket = [];
$materiales_frecuentes = [];
if ($es_mantenimiento) {
    // Materiales del ticket
    $sql = "SELECT * FROM mtto_tickets_materiales WHERE ticket_id = ? ORDER BY id";
    $materiales_ticket = $db->fetchAll($sql, [$ticket['id']]);
    
    // Materiales frecuentes
    $sql = "SELECT * FROM mtto_materiales_frecuentes WHERE activo = 1 ORDER BY nombre";
    $materiales_frecuentes = $db->fetchAll($sql);
}
?>

<style>
.modal-header-custom {
    background-color: #0E544C;
    color: white;
    padding: 1rem;
}

.info-row {
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.info-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
}

.urgency-selector-modal {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.75rem;
    border-radius: 4px;
    min-width: 110px;
    font-size: 0.8rem;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
}

.urgency-selector-modal:hover {
    opacity: 0.8;
    transform: scale(1.05);
}

.carousel-fotos {
    max-height: 400px;
}

.carousel-fotos img {
    max-height: 400px;
    object-fit: contain;
}

.material-item {
    display: flex;
    gap: 0.5rem;
    align-items: start;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 0.5rem;
    background-color: #f8f9fa;
}

.material-item.checked {
    background-color: #e8f5f3;
    border-color: #51B8AC;
}

.material-checkbox {
    margin-top: 0.5rem;
}

.colaborador-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 0.5rem;
    background-color: #f8f9fa;
}

.btn-foto-compact {
    padding: 0.35rem 0.75rem;
    font-size: 0.85rem;
}
</style>

<div class="modal-header modal-header-custom">
    <h5 class="modal-title">
        <?= $es_mantenimiento ? 'Mantenimiento General' : 'Solicitud de Equipos' ?>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
    <form id="formDetalleTicket">
        <input type="hidden" id="ticket_id_detalle" value="<?= $ticket['id'] ?>">
        
        <!-- Información básica -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="info-row">
                    <div class="info-label">Sucursal</div>
                    <div><?= htmlspecialchars($ticket['nombre_sucursal'] ?? 'N/A') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Título</div>
                    <?php if ($puedeEditar): ?>
                        <input type="text" class="form-control form-control-sm" id="titulo_detalle" 
                               value="<?= htmlspecialchars($ticket['titulo']) ?>" required>
                    <?php else: ?>
                        <div><?= htmlspecialchars($ticket['titulo']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="info-row">
                    <div class="info-label"><?= $label_area ?></div>
                    <?php if ($puedeEditar): ?>
                        <input type="text" class="form-control form-control-sm" id="area_equipo_detalle" 
                               value="<?= htmlspecialchars($ticket['area_equipo']) ?>" required>
                    <?php else: ?>
                        <div><?= htmlspecialchars($ticket['area_equipo']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="info-row">
                    <div class="info-label">Solicitante</div>
                    <div><?= htmlspecialchars($ticket['nombre_operario'] ?? 'N/A') ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Creado</div>
                    <div><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Nivel de Urgencia</div>
                    <div id="urgencia_container_detalle">
                        <!-- Se renderiza con JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Descripción -->
        <div class="mb-3">
            <label class="info-label">Descripción</label>
            <?php if ($puedeEditar): ?>
                <textarea class="form-control" id="descripcion_detalle" rows="3" required><?= htmlspecialchars($ticket['descripcion']) ?></textarea>
            <?php else: ?>
                <div class="form-control-plaintext"><?= nl2br(htmlspecialchars($ticket['descripcion'])) ?></div>
            <?php endif; ?>
        </div>
        
        <!-- Fotos -->
        <div class="mb-3">
            <label class="info-label mb-2">Fotos (<?= count($fotos) ?>)</label>
            <?php if (count($fotos) > 0): ?>
                <div id="carouselFotosDetalle" class="carousel slide carousel-fotos" data-bs-ride="false">
                    <div class="carousel-inner">
                        <?php foreach ($fotos as $index => $foto): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>" data-foto-id="<?= $foto['id'] ?>">
                                <img src="uploads/tickets/<?= $foto['foto'] ?>" class="d-block w-100" alt="Foto <?= $index + 1 ?>">
                                <?php if ($puedeEditar): ?>
                                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2" 
                                            onclick="eliminarFotoDetalle(<?= $foto['id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselFotosDetalle" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselFotosDetalle" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                </div>
            <?php else: ?>
                <p class="text-muted"><i class="bi bi-info-circle"></i> Sin fotos</p>
            <?php endif; ?>
            
            <?php if ($puedeEditar): ?>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-foto-compact" onclick="document.getElementById('nuevas_fotos_detalle').click()">
                        <i class="bi bi-upload"></i> Subir
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success btn-foto-compact" onclick="tomarFotoDetalle()">
                        <i class="bi bi-camera"></i> Tomar
                    </button>
                </div>
                <input type="file" id="nuevas_fotos_detalle" accept="image/*" multiple style="display: none;">
                <div id="preview_nuevas_fotos" class="mt-2" style="display: none;"></div>
            <?php endif; ?>
        </div>
        
        <!-- Colaboradores -->
        <div class="mb-3">
            <label class="info-label mb-2">Colaboradores Asignados</label>
            <div id="lista_colaboradores_detalle">
                <!-- Se carga con AJAX -->
            </div>
            <?php if ($puedeEditar): ?>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="agregarColaboradorDetalle()">
                    <i class="bi bi-plus"></i> Agregar Colaborador
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Materiales (solo para mantenimiento) -->
        <?php if ($es_mantenimiento && $puedeEditar): ?>
            <div class="mb-3">
                <label class="info-label mb-2">Materiales Utilizados</label>
                <input type="text" class="form-control form-control-sm mb-2" id="buscar_material" 
                       placeholder="Buscar material...">
                <div id="lista_materiales" style="max-height: 300px; overflow-y: auto;">
                    <!-- Se carga con AJAX -->
                </div>
                <div class="mt-2">
                    <label class="form-label">Otros materiales (texto libre)</label>
                    <textarea class="form-control" id="otros_materiales" rows="2" 
                              placeholder="Materiales adicionales no listados"></textarea>
                </div>
            </div>
        <?php endif; ?>
        
    </form>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <?php if ($puedeEditar): ?>
        <button type="button" class="btn btn-primary" style="background-color: #51B8AC; border-color: #51B8AC;" 
                onclick="guardarDetallesTicket()">
            <i class="bi bi-save"></i> Guardar Cambios
        </button>
    <?php endif; ?>
</div>

<script>
const ticketIdDetalle = <?= $ticket['id'] ?>;
const nivelUrgenciaActual = <?= $ticket['nivel_urgencia'] ?? 'null' ?>;
const puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;
const esMantenimiento = <?= $es_mantenimiento ? 'true' : 'false' ?>;

// Inicializar
$(document).ready(function() {
    renderizarUrgenciaDetalle();
    cargarColaboradoresDetalle();
    if (esMantenimiento && puedeEditar) {
        cargarMaterialesDetalle();
    }
    
    // Buscar materiales
    $('#buscar_material').on('input', function() {
        filtrarMateriales($(this).val());
    });
});

// Renderizar selector de urgencia
function renderizarUrgenciaDetalle() {
    const container = $('#urgencia_container_detalle');
    const colores = {
        1: '#28a745',
        2: '#ffc107',
        3: '#fd7e14',
        4: '#dc3545',
        null: '#8b8b8bff'
    };
    const textos = {
        1: 'No Urgente',
        2: 'Medio',
        3: 'Urgente',
        4: 'Crítico',
        null: 'No Clasificado'
    };
    
    const nivel = nivelUrgenciaActual || null;
    const color = colores[nivel];
    const texto = textos[nivel];
    
    if (puedeEditar) {
        container.html(`
            <div class="urgency-selector-modal" style="background-color: ${color};" onclick="cambiarUrgenciaDetalle()">
                ${texto}
            </div>
        `);
    } else {
        container.html(`<span class="badge" style="background-color: ${color};">${texto}</span>`);
    }
}

// Cargar colaboradores
function cargarColaboradoresDetalle() {
    $.ajax({
        url: 'ajax/detalles_get_colaboradores.php',
        method: 'GET',
        data: { ticket_id: ticketIdDetalle },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarColaboradoresDetalle(response.colaboradores, response.operarios);
            }
        }
    });
}

/// JavaScript completo para el modal de detalles - Agregar al final del archivo get_ticket_details.php

let operariosDisponibles = [];
let nuevasFotosDetalle = [];

// Cambiar urgencia
function cambiarUrgenciaDetalle() {
    const opciones = [
        { nivel: 0, texto: 'No Clasificado', color: '#8b8b8bff' },
        { nivel: 1, texto: 'No Urgente', color: '#28a745' },
        { nivel: 2, texto: 'Medio', color: '#ffc107' },
        { nivel: 3, texto: 'Urgente', color: '#fd7e14' },
        { nivel: 4, texto: 'Crítico', color: '#dc3545' }
    ];
    
    let html = '<div style="padding: 0.5rem;">';
    html += '<div style="font-weight: 600; margin-bottom: 0.5rem;">Seleccionar nivel:</div>';
    
    opciones.forEach(opt => {
        html += `
            <div style="padding: 0.5rem 0.75rem; cursor: pointer; border-radius: 3px; margin-bottom: 0.25rem; 
                        background-color: ${opt.color}; color: white; text-align: center; font-weight: 600;" 
                 onmouseover="this.style.opacity='0.8'" 
                 onmouseout="this.style.opacity='1'"
                 onclick="seleccionarUrgenciaDetalle(${opt.nivel})">
                ${opt.texto}
            </div>
        `;
    });
    
    html += '</div>';
    
    const modalHtml = `
        <div class="modal fade" id="modalUrgenciaDetalle" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #0E544C; color: white; padding: 0.75rem 1rem;">
                        <h6 class="modal-title mb-0">Nivel de Urgencia</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">${html}</div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('modalUrgenciaDetalle'));
    modal.show();
    
    $('#modalUrgenciaDetalle').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

function seleccionarUrgenciaDetalle(nivel) {
    $('#modalUrgenciaDetalle').modal('hide');
    window.nivelUrgenciaActual = nivel;
    renderizarUrgenciaDetalle();
}

// Renderizar colaboradores
function renderizarColaboradoresDetalle(colaboradores, operarios) {
    operariosDisponibles = operarios;
    const container = $('#lista_colaboradores_detalle');
    container.empty();
    
    if (colaboradores.length === 0) {
        container.html('<p class="text-muted"><i class="bi bi-info-circle"></i> Sin colaboradores asignados</p>');
        return;
    }
    
    colaboradores.forEach(col => {
        const div = $('<div class="colaborador-row">');
        
        // Select de operario
        let selectHtml = '<select class="form-select form-select-sm" onchange="actualizarColaboradorDetalle(' + col.id + ', this.value)">';
        selectHtml += '<option value="">Seleccionar...</option>';
        operarios.forEach(op => {
            const selected = op.CodOperario == col.cod_operario ? 'selected' : '';
            selectHtml += `<option value="${op.CodOperario}" ${selected}>${op.nombre_completo}</option>`;
        });
        selectHtml += '</select>';
        
        div.html(`
            <div style="flex: 2;">${selectHtml}</div>
            <div style="flex: 1;"><span class="badge bg-secondary">${col.tipo_usuario}</span></div>
            <button class="btn btn-danger btn-sm" onclick="eliminarColaboradorDetalle(${col.id})">
                <i class="bi bi-x"></i>
            </button>
        `);
        
        container.append(div);
    });
}

function agregarColaboradorDetalle() {
    const tipos = ['Jefe de Manteniento', 'Lider de Infraestructura', 'Conductor', 'Auxiliar de Mantenimiento'];
    
    let html = '<div style="padding: 1rem;">';
    html += '<label class="form-label">Seleccionar tipo:</label>';
    html += '<select class="form-select" id="nuevo_tipo_colaborador">';
    html += '<option value="">Seleccionar...</option>';
    tipos.forEach(tipo => {
        html += `<option value="${tipo}">${tipo}</option>`;
    });
    html += '</select></div>';
    
    const modalHtml = `
        <div class="modal fade" id="modalNuevoColaborador" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #0E544C; color: white;">
                        <h6 class="modal-title mb-0">Agregar Colaborador</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">${html}</div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary btn-sm" onclick="guardarNuevoColaboradorDetalle()">Agregar</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('modalNuevoColaborador'));
    modal.show();
    
    $('#modalNuevoColaborador').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

function guardarNuevoColaboradorDetalle() {
    const tipo = $('#nuevo_tipo_colaborador').val();
    
    if (!tipo) {
        alert('Debe seleccionar un tipo de colaborador');
        return;
    }
    
    $.ajax({
        url: 'ajax/detalles_save_colaborador.php',
        method: 'POST',
        data: {
            ticket_id: ticketIdDetalle,
            tipo_usuario: tipo,
            cod_operario: null
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#modalNuevoColaborador').modal('hide');
                cargarColaboradoresDetalle();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function actualizarColaboradorDetalle(colaboradorId, codOperario) {
    $.ajax({
        url: 'ajax/agenda_update_colaborador.php',
        method: 'POST',
        data: {
            colaborador_id: colaboradorId,
            cod_operario: codOperario
        },
        dataType: 'json'
    });
}

function eliminarColaboradorDetalle(colaboradorId) {
    if (!confirm('¿Eliminar este colaborador?')) return;
    
    $.ajax({
        url: 'ajax/detalles_delete_colaborador.php',
        method: 'POST',
        data: { colaborador_id: colaboradorId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                cargarColaboradoresDetalle();
            }
        }
    });
}

// Materiales
function cargarMaterialesDetalle() {
    $.ajax({
        url: 'ajax/detalles_get_materiales.php',
        method: 'GET',
        data: { ticket_id: ticketIdDetalle },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderizarMaterialesDetalle(response.materiales_frecuentes, response.materiales_usados);
            }
        }
    });
}

function renderizarMaterialesDetalle(frecuentes, usados) {
    const container = $('#lista_materiales');
    container.empty();
    
    // Ordenar: usados primero
    const materialesOrdenados = frecuentes.sort((a, b) => {
        const aUsado = usados[a.id] ? 1 : 0;
        const bUsado = usados[b.id] ? 1 : 0;
        return bUsado - aUsado || a.nombre.localeCompare(b.nombre);
    });
    
    materialesOrdenados.forEach(material => {
        const usado = usados[material.id];
        const checked = usado ? 'checked' : '';
        const checkedClass = usado ? 'checked' : '';
        
        const div = $(`<div class="material-item ${checkedClass}" data-material-id="${material.id}">`);
        
        div.html(`
            <input type="checkbox" class="material-checkbox" ${checked} 
                   onchange="toggleMaterialDetalle(this, ${material.id}, '${material.nombre}')">
            <div style="flex: 2;">
                <strong>${material.nombre}</strong>
                <input type="text" class="form-control form-control-sm mt-1" 
                       placeholder="Detalle..." value="${usado ? (usado.detalle || '') : ''}"
                       onchange="actualizarDetalleMaterial(${material.id}, this.value)">
            </div>
            <div style="flex: 1;">
                <select class="form-select form-select-sm" 
                        onchange="actualizarProcedenciaMaterial(${material.id}, this.value)">
                    <option value="">Procedencia...</option>
                    <option value="Bodega Villa" ${usado && usado.procedencia === 'Bodega Villa' ? 'selected' : ''}>Bodega Villa</option>
                    <option value="Bodega Altamira" ${usado && usado.procedencia === 'Bodega Altamira' ? 'selected' : ''}>Bodega Altamira</option>
                    <option value="Compra Sinsa" ${usado && usado.procedencia === 'Compra Sinsa' ? 'selected' : ''}>Compra Sinsa</option>
                    <option value="Compra Ferreteria" ${usado && usado.procedencia === 'Compra Ferreteria' ? 'selected' : ''}>Compra Ferreteria</option>
                    <option value="Otros" ${usado && usado.procedencia === 'Otros' ? 'selected' : ''}>Otros</option>
                </select>
            </div>
        `);
        
        container.append(div);
    });
}

function toggleMaterialDetalle(checkbox, materialId, materialNombre) {
    const item = $(checkbox).closest('.material-item');
    if (checkbox.checked) {
        item.addClass('checked');
    } else {
        item.removeClass('checked');
    }
}

function filtrarMateriales(busqueda) {
    const texto = busqueda.toLowerCase();
    $('.material-item').each(function() {
        const nombre = $(this).find('strong').text().toLowerCase();
        $(this).toggle(nombre.includes(texto));
    });
}

// Fotos
$('#nuevas_fotos_detalle').on('change', function(e) {
    const files = Array.from(e.target.files);
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
            nuevasFotosDetalle.push({ tipo: 'file', data: e.target.result, file: file });
            mostrarPreviewFotos();
        };
        reader.readAsDataURL(file);
    });
});

function tomarFotoDetalle() {
    // Implementar lógica de cámara similar al original
    alert('Función de cámara pendiente de implementar');
}

function eliminarFotoDetalle(fotoId) {
    if (!confirm('¿Eliminar esta foto?')) return;
    
    $.ajax({
        url: 'ajax/delete_ticket_photo.php',
        method: 'POST',
        data: { foto_id: fotoId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(`.carousel-item[data-foto-id="${fotoId}"]`).remove();
                // Activar el primer item si existe
                $('.carousel-item').first().addClass('active');
            }
        }
    });
}

function mostrarPreviewFotos() {
    const container = $('#preview_nuevas_fotos');
    container.empty().show();
    
    nuevasFotosDetalle.forEach((foto, index) => {
        container.append(`
            <div class="d-inline-block position-relative m-1">
                <img src="${foto.data}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">
                <button class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                        onclick="eliminarNuevaFotoDetalle(${index})">×</button>
            </div>
        `);
    });
}

function eliminarNuevaFotoDetalle(index) {
    nuevasFotosDetalle.splice(index, 1);
    mostrarPreviewFotos();
}

// Guardar todo
function guardarDetallesTicket() {
    const formData = new FormData();
    formData.append('ticket_id', ticketIdDetalle);
    formData.append('titulo', $('#titulo_detalle').val());
    formData.append('area_equipo', $('#area_equipo_detalle').val());
    formData.append('descripcion', $('#descripcion_detalle').val());
    formData.append('nivel_urgencia', window.nivelUrgenciaActual || '');
    
    // Agregar nuevas fotos
    nuevasFotosDetalle.forEach((foto, index) => {
        if (foto.tipo === 'file') {
            formData.append('nuevas_fotos[]', foto.file);
        }
    });
    
    const fotosCamera = nuevasFotosDetalle.filter(f => f.tipo === 'camera').map(f => f.data);
    if (fotosCamera.length > 0) {
        formData.append('fotos_camera', JSON.stringify(fotosCamera));
    }
    
    // Guardar ticket
    $.ajax({
        url: 'ajax/detalles_update_ticket.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Guardar materiales si es mantenimiento
                if (esMantenimiento) {
                    guardarMaterialesDetalle();
                } else {
                    cerrarModalYRecargar();
                }
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function guardarMaterialesDetalle() {
    const materiales = [];
    
    $('.material-item input[type="checkbox"]:checked').each(function() {
        const item = $(this).closest('.material-item');
        const materialId = item.data('material-id');
        const nombre = item.find('strong').text();
        const detalle = item.find('input[type="text"]').val();
        const procedencia = item.find('select').val();
        
        materiales.push({
            material_id: materialId,
            nombre: nombre,
            detalle: detalle,
            procedencia: procedencia
        });
    });
    
    // Agregar "otros"
    const otros = $('#otros_materiales').val().trim();
    if (otros) {
        materiales.push({
            material_id: null,
            nombre: 'Otros',
            detalle: otros,
            procedencia: 'Otros'
        });
    }
    
    $.ajax({
        url: 'ajax/detalles_save_materiales.php',
        method: 'POST',
        data: {
            ticket_id: ticketIdDetalle,
            materiales: JSON.stringify(materiales)
        },
        dataType: 'json',
        success: function(response) {
            cerrarModalYRecargar();
        }
    });
}

function cerrarModalYRecargar() {
    const modalElement = document.querySelector('.modal.show');
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) modal.hide();
    }
    
    if (typeof refrescarCalendarioYSidebar === 'function') {
        refrescarCalendarioYSidebar();
    } else {
        location.reload();
    }
}
</script>