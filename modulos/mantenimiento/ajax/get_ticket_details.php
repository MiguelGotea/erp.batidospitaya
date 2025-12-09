<?php

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../models/Ticket.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/funciones.php';

if (!isset($_GET['id'])) {
    die('ID de ticket requerido');
}

$ticket_model = new Ticket();
$ticket = $ticket_model->getById($_GET['id']);
$tipos_casos = $ticket_model->getTiposCasos();
$fotos = $ticket_model->getFotos($_GET['id']);

// Verificar permisos del usuario
session_start();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$puedeEditar = $esAdmin || verificarAccesoCargo([14, 16, 35]);
$esLider = verificarAccesoCargo([5]);

if ($esLider && !$esAdmin && !verificarAccesoCargo([14, 16, 35])) {
    require_once '../../../includes/funciones.php';
    $sucursalesLider = obtenerSucursalesLider($_SESSION['usuario_id']);
    $codigosSucursales = array_column($sucursalesLider, 'codigo');
    
    if (!in_array($ticket['cod_sucursal'], $codigosSucursales)) {
        die('No tienes permiso para ver este ticket');
    }
}

if (!$ticket) {
    die('Ticket no encontrado');
}

$encabezado = '';
if ($ticket['tipo_formulario'] === 'mantenimiento_general') {
    $encabezado = 'Mantenimiento General';
} elseif ($ticket['tipo_formulario'] === 'cambio_equipos') {
    $encabezado = 'Solicitud de Equipos';
} else {
    $encabezado = 'Otras Solicitudes';
}

?>

<form id="editTicketForm" onsubmit="updateTicket(event)">
    <input type="hidden" id="ticket_id" value="<?= $ticket['id'] ?>">
    
    <div class="form-header bg-primary text-white p-3 rounded mb-4" style="background-color: #0E544C !important;">
        <h4 class="text-center fw-bold mb-0">
            <?= $encabezado ?>
        </h4>
    </div>

    <div class="row">
        <div class="col-md-6">
                    
            <div class="mb-3">
                <label class="form-label"><strong>Sucursal:</strong></label>
                <p class="form-control-plaintext"><?= htmlspecialchars($ticket['nombre_sucursal'] ?? 'N/A') ?></p>
            </div>

            <div class="mb-3">
                <label for="edit_titulo" class="form-label"><strong>Título:</strong></label>
                <input type="text" class="form-control" id="edit_titulo" name="titulo" 
                        value="<?= htmlspecialchars($ticket['titulo']) ?>" 
                   <?= !$puedeEditar ? 'readonly' : '' ?> required>
            </div>

            <div class="mb-3">
                <label class="form-label"><strong>Creado:</strong></label>
                <p class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></p>
            </div>

        </div>
        
        <div class="col-md-6">
            
            <div class="mb-3">
                <label class="form-label"><strong>Solicitante:</strong></label>
                <p class="form-control-plaintext"><?= htmlspecialchars($ticket['nombre_operario'] ?? 'N/A') ?></p>
            </div>

            <div class="mb-3">
                <label for="edit_areaequipo" class="form-label"><strong>Área/Equipo:</strong></label>
                <input type="text" class="form-control" id="edit_areaequipo" name="area_equipo" 
                       value="<?= htmlspecialchars($ticket['area_equipo']) ?>" 
                       <?= !$puedeEditar ? 'readonly' : '' ?> required>
            </div>

            <div class="mb-3">
                <label class="form-label"><strong>Nivel de Urgencia:</strong></label>
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex flex-grow-1 urgency-compact-buttons">
                        <button type="button" class="btn btn-sm urgency-compact urgency-level-1 <?= ($ticket['nivel_urgencia'] == 1) ? 'selected' : '' ?>" 
                                data-level="1" onclick="selectUrgency(1)" title="Baja" <?= !$puedeEditar ? 'disabled' : '' ?>>
                            <small>1</small>
                        </button>
                        <button type="button" class="btn btn-sm urgency-compact urgency-level-2 <?= ($ticket['nivel_urgencia'] == 2) ? 'selected' : '' ?>" 
                                data-level="2" onclick="selectUrgency(2)" title="Media" <?= !$puedeEditar ? 'disabled' : '' ?>>
                            <small>2</small>
                        </button>
                        <button type="button" class="btn btn-sm urgency-compact urgency-level-3 <?= ($ticket['nivel_urgencia'] == 3) ? 'selected' : '' ?>" 
                                data-level="3" onclick="selectUrgency(3)" title="Alta" <?= !$puedeEditar ? 'disabled' : '' ?>>
                            <small>3</small>
                        </button>
                        <button type="button" class="btn btn-sm urgency-compact urgency-level-4 <?= ($ticket['nivel_urgencia'] == 4) ? 'selected' : '' ?>" 
                                data-level="4" onclick="selectUrgency(4)" title="Crítica" <?= !$puedeEditar ? 'disabled' : '' ?>>
                            <small>4</small>
                        </button>
                    </div>
                    
                    <span id="urgency_display" class="badge fs-6 ms-2 flex-shrink-0">
                        <?php 
                        if ($ticket['nivel_urgencia']) {
                            echo 'Nivel ' . $ticket['nivel_urgencia'];
                        } else {
                            echo 'No seleccionado';
                        }
                        ?>
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0" 
                            onclick="clearUrgency()" title="Limpiar" <?= !$puedeEditar ? 'disabled' : '' ?>>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <input type="hidden" id="edit_nivel_urgencia" name="nivel_urgencia" value="<?= $ticket['nivel_urgencia'] ?? '' ?>">
            </div>
            
        </div>
    </div>
 
    <div class="mb-3">
        <label for="edit_descripcion" class="form-label"><strong>Descripción:</strong></label>
        <textarea class="form-control" style="min-height: 100px; max-height: 150px; overflow-y: auto;" 
                id="edit_descripcion" name="descripcion" 
                <?= !$puedeEditar ? 'readonly' : '' ?> required><?= htmlspecialchars($ticket['descripcion']) ?></textarea>
    </div>
    
    <?php if (!empty($fotos)): ?>
        <div class="mb-3">
            <label class="form-label"><strong>Evidencias Fotográficas (<?= count($fotos) ?>):</strong></label>
            <div class="photos-grid-edit">
                <?php foreach ($fotos as $index => $foto): ?>
                    <div class="photo-item-edit" data-foto-id="<?= $foto['id'] ?>">
                        <img src="uploads/tickets/<?= $foto['foto'] ?>" alt="Foto <?= $index + 1 ?>" 
                             class="img-thumbnail"
                             onclick="showPhotoFullscreen('uploads/tickets/<?= $foto['foto'] ?>', <?= $index + 1 ?>, <?= count($fotos) ?>)">
                        <div class="photo-overlay">
                            <small class="text-white">Foto <?= $index + 1 ?></small>
                            <?php if ($puedeEditar): ?>
                                <button type="button" class="btn btn-danger btn-sm mt-1" 
                                        onclick="deletePhoto(<?= $foto['id'] ?>, <?= $ticket['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-3">
            <p class="text-muted"><i class="fas fa-info-circle me-2"></i>No hay fotografías adjuntas a este ticket</p>
        </div>
    <?php endif; ?>

    <?php if ($puedeEditar): ?>
        <div class="mb-3">
            <label class="form-label"><strong>Agregar nuevas fotos (opcional):</strong></label>
            <div class="d-flex gap-2 mb-2">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('nuevas_fotos').click()">
                    <i class="fas fa-upload me-1"></i>Subir Archivos
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" id="btnCameraEdit">
                    <i class="fas fa-camera me-1"></i>Tomar Foto
                </button>
            </div>
            <input type="file" id="nuevas_fotos" name="nuevas_fotos[]" accept="image/*" multiple style="display: none;">
            <input type="hidden" id="nuevas_fotos_camera" name="nuevas_fotos_camera">
            
            <div class="camera-preview" id="cameraPreviewEdit" style="display: none; max-width: 300px; margin: 10px 0;">
                <video id="videoEdit" autoplay style="width: 100%; border-radius: 8px;"></video>
                <canvas id="canvasEdit" style="display: none;"></canvas>
            </div>
            
            <div id="nuevasFotosPreview" style="display: none; margin-top: 10px;">
                <div id="nuevasFotosList" class="photos-grid-edit"></div>
            </div>
            <small class="text-muted">Puedes agregar más fotos al ticket (máximo 5 fotos nuevas)</small>
        </div>
    <?php endif; ?>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>        
        <button type="button" class="btn btn-info" onclick="openChatFromModal(<?= $ticket['id'] ?>)">
            <i class="fas fa-comments me-2"></i>Abrir Chat
        </button>
        <?php if ($puedeEditar): ?>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Guardar Cambios
        </button>
        <?php endif; ?>
    </div>
</form>

<style>
.photos-grid-edit {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.photo-item-edit {
    position: relative;
    cursor: pointer;
}

.photo-item-edit img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
}

.photo-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
    padding: 8px;
    border-radius: 0 0 8px 8px;
    text-align: center;
}

.urgency-compact {
    flex: 1;
    padding: 4px 8px;
    border: 2px solid transparent;
    border-radius: 4px;
    transition: all 0.2s ease;
    min-width: 35px;
    color: white;
    font-weight: 500;
}
.urgency-compact:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    filter: brightness(1.1);
}
.urgency-compact.selected {
    border-color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.5) !important;
    font-weight: bold;
}
.urgency-compact:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
.urgency-compact-buttons {
    gap: 2px;
}
.urgency-level-1 {
    background-color: #28a745;
    border: 1px solid #218838;
}
.urgency-level-2 {
    background-color: #ffc107;
    border: 1px solid #e0a800;
    color: #000;
}
.urgency-level-3 {
    background-color: #fd7e14;
    border: 1px solid #e56a00;
}
.urgency-level-4 {
    background-color: #dc3545;
    border: 1px solid #c82333;
    font-weight: bold;
}

.badge.bg-success { background-color: #28a745 !important; }
.badge.bg-warning { background-color: #ffc107 !important; color: #000 !important; }
.badge.bg-orange { background-color: #fd7e14 !important; }
.badge.bg-danger { background-color: #dc3545 !important; }
.badge.bg-secondary { background-color: #6c757d !important; }
</style>

<script>
window.currentUrgency = <?= $ticket['nivel_urgencia'] ? $ticket['nivel_urgencia'] : 'null' ?>;
let streamEdit = null;
let nuevasFotosSeleccionadas = [];
const MAX_NUEVAS_FOTOS = 5;

function initUrgencyControls() {
    updateUrgencyDisplay();
    updateOptions(window.currentUrgency);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUrgencyControls);
} else {
    initUrgencyControls();
}

function selectUrgency(level) {
    window.currentUrgency = level;
    updateUrgencyDisplay();
    updateOptions(level);
}

function updateUrgencyDisplay() {
    const display = document.getElementById('urgency_display');
    const hiddenInput = document.getElementById('edit_nivel_urgencia');
    
    if (!display || !hiddenInput) return;
    
    if (window.currentUrgency) {
        const levels = {
            1: { text: 'Baja', badgeClass: 'bg-success' },
            2: { text: 'Media', badgeClass: 'bg-warning' },
            3: { text: 'Alta', badgeClass: 'bg-orange' },
            4: { text: 'Crítica', badgeClass: 'bg-danger' }
        };
        
        const levelInfo = levels[window.currentUrgency];
        display.textContent = `Nivel ${window.currentUrgency}`;
        display.className = `badge fs-6 ms-2 flex-shrink-0 ${levelInfo.badgeClass}`;
        hiddenInput.value = window.currentUrgency;
    } else {
        display.textContent = 'No seleccionado';
        display.className = 'badge fs-6 ms-2 flex-shrink-0 bg-secondary';
        hiddenInput.value = '';
    }
}

function updateOptions(level) {
    document.querySelectorAll('.urgency-compact').forEach(option => {
        option.classList.remove('selected');
    });
    
    if (level) {
        const selectedOption = document.querySelector(`.urgency-compact[data-level="${level}"]`);
        if (selectedOption) {
            selectedOption.classList.add('selected');
        }
    }
}

function clearUrgency() {
    window.currentUrgency = null;
    updateUrgencyDisplay();
    updateOptions(null);
}

// Manejo de nuevas fotos
document.getElementById('nuevas_fotos')?.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    
    if (nuevasFotosSeleccionadas.length + files.length > MAX_NUEVAS_FOTOS) {
        alert(`Solo puedes agregar hasta ${MAX_NUEVAS_FOTOS} fotos nuevas`);
        return;
    }
    
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
            nuevasFotosSeleccionadas.push({
                tipo: 'file',
                data: e.target.result,
                file: file
            });
            updateNuevasFotosPreview();
        };
        reader.readAsDataURL(file);
    });
});

document.getElementById('btnCameraEdit')?.addEventListener('click', function() {
    if (nuevasFotosSeleccionadas.length >= MAX_NUEVAS_FOTOS) {
        alert(`Ya has alcanzado el límite de ${MAX_NUEVAS_FOTOS} fotos nuevas`);
        return;
    }
    
    if (streamEdit) {
        stopCameraEdit();
    } else {
        startCameraEdit();
    }
});

function startCameraEdit() {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function(mediaStream) {
            streamEdit = mediaStream;
            const video = document.getElementById('videoEdit');
            video.srcObject = streamEdit;
            document.getElementById('cameraPreviewEdit').style.display = 'block';
            
            if (!document.getElementById('captureBtnEdit')) {
                const captureBtn = document.createElement('button');
                captureBtn.type = 'button';
                captureBtn.id = 'captureBtnEdit';
                captureBtn.className = 'btn btn-success btn-sm mt-2 w-100';
                captureBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Capturar Foto';
                captureBtn.addEventListener('click', capturePhotoEdit);
                document.getElementById('cameraPreviewEdit').appendChild(captureBtn);
            }
        })
        .catch(function(err) {
            alert('Error al acceder a la cámara: ' + err.message);
        });
}

function capturePhotoEdit() {
    if (nuevasFotosSeleccionadas.length >= MAX_NUEVAS_FOTOS) {
        alert(`Ya has alcanzado el límite de ${MAX_NUEVAS_FOTOS} fotos nuevas`);
        stopCameraEdit();
        return;
    }
    
    const video = document.getElementById('videoEdit');
    const canvas = document.getElementById('canvasEdit');
    const context = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0);
    
    const dataURL = canvas.toDataURL('image/jpeg');
    
    nuevasFotosSeleccionadas.push({
        tipo: 'camera',
        data: dataURL
    });
    
    updateNuevasFotosPreview();
    stopCameraEdit();
}

function stopCameraEdit() {
    if (streamEdit) {
        streamEdit.getTracks().forEach(track => track.stop());
        streamEdit = null;
    }
    document.getElementById('cameraPreviewEdit').style.display = 'none';
    const captureBtn = document.getElementById('captureBtnEdit');
    if (captureBtn) captureBtn.remove();
}

function updateNuevasFotosPreview() {
    const previewContainer = document.getElementById('nuevasFotosPreview');
    const fotosList = document.getElementById('nuevasFotosList');
    
    if (nuevasFotosSeleccionadas.length === 0) {
        previewContainer.style.display = 'none';
        return;
    }
    
    previewContainer.style.display = 'block';
    fotosList.innerHTML = '';
    
    nuevasFotosSeleccionadas.forEach((foto, index) => {
        const div = document.createElement('div');
        div.className = 'photo-item-edit';
        div.innerHTML = `
            <img src="${foto.data}" class="img-thumbnail">
            <div class="photo-overlay">
                <small class="text-white">Nueva ${index + 1}</small>
                <button type="button" class="btn btn-danger btn-sm mt-1" onclick="removeNuevaFoto(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        fotosList.appendChild(div);
    });
    
    updateNuevasFotosInputs();
}

function removeNuevaFoto(index) {
    nuevasFotosSeleccionadas.splice(index, 1);
    updateNuevasFotosPreview();
}

function updateNuevasFotosInputs() {
    const dt = new DataTransfer();
    const fotosCamera = [];
    
    nuevasFotosSeleccionadas.forEach(foto => {
        if (foto.tipo === 'file') {
            dt.items.add(foto.file);
        } else if (foto.tipo === 'camera') {
            fotosCamera.push(foto.data);
        }
    });
    
    document.getElementById('nuevas_fotos').files = dt.files;
    document.getElementById('nuevas_fotos_camera').value = JSON.stringify(fotosCamera);
}

function deletePhoto(fotoId, ticketId) {
    if (!confirm('¿Estás seguro de eliminar esta foto? Esta acción no se puede deshacer.')) {
        return;
    }
    
    $.ajax({
        url: 'ajax/delete_ticket_photo.php',
        method: 'POST',
        data: { foto_id: fotoId, ticket_id: ticketId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(`.photo-item-edit[data-foto-id="${fotoId}"]`).fadeOut(300, function() {
                    $(this).remove();
                });
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error al eliminar la foto');
        }
    });
}

function showPhotoFullscreen(src, current, total) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Foto ${current} de ${total}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="${src}" alt="Imagen completa" class="img-fluid" style="max-height: 80vh;">
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

function updateTicket(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('id', document.getElementById('ticket_id').value);
    formData.append('nivel_urgencia', window.currentUrgency || '');
    
    // Agregar nuevas fotos si existen
    if (nuevasFotosSeleccionadas.length > 0) {
        nuevasFotosSeleccionadas.forEach((foto, index) => {
            if (foto.tipo === 'file') {
                formData.append('nuevas_fotos[]', foto.file);
            }
        });
        
        const fotosCamera = nuevasFotosSeleccionadas
            .filter(f => f.tipo === 'camera')
            .map(f => f.data);
        if (fotosCamera.length > 0) {
            formData.append('nuevas_fotos_camera', JSON.stringify(fotosCamera));
        }
    }
    
    $.ajax({
        url: 'ajax/update_ticket.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const modalElement = document.querySelector('.modal.show');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                }
                
                if (typeof refrescarCalendarioYSidebar === 'function') {
                    refrescarCalendarioYSidebar();
                } else {
                    location.reload();
                }
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', xhr.responseText);
            alert('Error en la comunicación con el servidor');
        }
    });
}

function openChatFromModal(ticketId) {
    const modalElement = document.querySelector('.modal.show');
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }
    window.open('chat.php?ticket_id=' + ticketId + '&emisor=mantenimiento', '_blank');
}
</script>