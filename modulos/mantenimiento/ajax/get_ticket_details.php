<?php

// ✅ Evitar caché del navegador
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

// Verificar permisos del usuario
session_start();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$puedeEditar = $esAdmin || verificarAccesoCargo([14, 16, 35]);
$esLider = verificarAccesoCargo([5]);

// Si es líder, verificar que el ticket sea de su sucursal
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

// Determinar el encabezado según el tipo de formulario
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
    
    <!-- Encabezado del formulario -->
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

            <!-- SELECTOR COMPACTO DE URGENCIA EN UNA LÍNEA -->
            <div class="mb-3">
                <label class="form-label"><strong>Nivel de Urgencia:</strong></label>
                <div class="d-flex align-items-center gap-2">
                    <!-- Botones compactos de urgencia -->
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
                    
                    <!-- Display y botón clear -->
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
                
                <!-- Input oculto para el formulario -->
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
    
    <?php if ($ticket['foto']): ?>
        <div class="mb-3">
            <label class="form-label"><strong>Evidencias:</strong></label>
            <div>
                <img src="uploads/tickets/<?= $ticket['foto'] ?>" alt="Foto del ticket" 
                     class="img-thumbnail" style="max-width: 300px; max-height: 200px;">
            </div>
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

/* Estilos para el badge de display */
.badge.bg-success { background-color: #28a745 !important; }
.badge.bg-warning { background-color: #ffc107 !important; color: #000 !important; }
.badge.bg-orange { background-color: #fd7e14 !important; }
.badge.bg-danger { background-color: #dc3545 !important; }
.badge.bg-secondary { background-color: #6c757d !important; }
</style>

<script>
// Variables globales para el control de urgencia
let currentUrgency = <?= $ticket['nivel_urgencia'] ? $ticket['nivel_urgencia'] : 'null' ?>;

console.log('Script cargado para ticket:', <?= $ticket['id'] ?>);
console.log('Urgencia inicial:', currentUrgency);

// ✅ SOLUCIÓN: Inicializar después de que el modal esté completamente cargado
function initUrgencyControls() {
    console.log('Inicializando controles de urgencia...');
    updateUrgencyDisplay();
    updateOptions(currentUrgency);
    console.log('Urgencia configurada:', currentUrgency);
}

// Ejecutar cuando el DOM del modal esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUrgencyControls);
} else {
    // El DOM ya está listo, ejecutar inmediatamente
    initUrgencyControls();
}

function selectUrgency(level) {
    console.log('Seleccionando urgencia:', level);
    currentUrgency = level;
    updateUrgencyDisplay();
    updateOptions(level);
}

function updateUrgencyDisplay() {
    const display = document.getElementById('urgency_display');
    const hiddenInput = document.getElementById('edit_nivel_urgencia');
    
    if (!display || !hiddenInput) {
        console.error('Elementos no encontrados. Display:', display, 'Input:', hiddenInput);
        return;
    }
    
    if (currentUrgency) {
        const levels = {
            1: { text: 'Baja', badgeClass: 'bg-success' },
            2: { text: 'Media', badgeClass: 'bg-warning' },
            3: { text: 'Alta', badgeClass: 'bg-orange' },
            4: { text: 'Crítica', badgeClass: 'bg-danger' }
        };
        
        const levelInfo = levels[currentUrgency];
        display.textContent = `Nivel ${currentUrgency}`;
        display.className = `badge fs-6 ms-2 flex-shrink-0 ${levelInfo.badgeClass}`;
        
        hiddenInput.value = currentUrgency;
        console.log('Display actualizado a:', currentUrgency);
    } else {
        display.textContent = 'No seleccionado';
        display.className = 'badge fs-6 ms-2 flex-shrink-0 bg-secondary';
        hiddenInput.value = '';
        console.log('Display limpiado');
    }
}

function updateOptions(level) {
    // Remover selección de todas las opciones
    document.querySelectorAll('.urgency-compact').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Agregar selección a la opción actual
    if (level) {
        const selectedOption = document.querySelector(`.urgency-compact[data-level="${level}"]`);
        if (selectedOption) {
            selectedOption.classList.add('selected');
            console.log('Opción marcada como seleccionada:', level);
        } else {
            console.error('No se encontró el botón para nivel:', level);
        }
    }
}

function clearUrgency() {
    console.log('Limpiando urgencia');
    currentUrgency = null;
    updateUrgencyDisplay();
    updateOptions(null);
}

function updateTicket(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const ticketId = formData.get('ticket_id') || document.getElementById('ticket_id').value;
    
    // Asegurarnos de incluir el nivel de urgencia actual
    const data = {
        id: ticketId,
        titulo: formData.get('titulo'),
        descripcion: formData.get('descripcion'),
        area_equipo: formData.get('area_equipo'),
        nivel_urgencia: currentUrgency, // Usamos la variable global actualizada
    };
    
    console.log('Guardando ticket con datos:', data);
    
    $.ajax({
        url: 'ajax/update_ticket.php',
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Cerrar el modal
                const modalElement = document.querySelector('.modal.show');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                }
                
                // Refrescar calendario sin recargar página
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