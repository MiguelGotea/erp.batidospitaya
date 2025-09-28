<?php
require_once '../models/Ticket.php';

if (!isset($_GET['id'])) {
    die('ID de ticket requerido');
}

$ticket_model = new Ticket();
$ticket = $ticket_model->getById($_GET['id']);
$tipos_casos = $ticket_model->getTiposCasos();

if (!$ticket) {
    die('Ticket no encontrado');
}
?>

<form id="editTicketForm" onsubmit="updateTicket(event)">
    <input type="hidden" id="ticket_id" value="<?= $ticket['id'] ?>">
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label"><strong>Código:</strong></label>
                <p class="form-control-plaintext"><?= htmlspecialchars($ticket['codigo']) ?></p>
            </div>
            
            <div class="mb-3">
                <label for="edit_titulo" class="form-label"><strong>Título:</strong></label>
                <input type="text" class="form-control" id="edit_titulo" name="titulo" 
                       value="<?= htmlspecialchars($ticket['titulo']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label"><strong>Tipo:</strong></label>
                <p class="form-control-plaintext">
                    <span class="badge bg-info">
                        <?= $ticket['tipo_formulario'] === 'mantenimiento_general' ? 'Mantenimiento General' : 'Cambio de Equipos' ?>
                    </span>
                </p>
            </div>
            
            <div class="mb-3">
                <label class="form-label"><strong>Solicitante:</strong></label>
                <p class="form-control-plaintext"><?= htmlspecialchars($ticket['nombre_operario'] ?? 'N/A') ?></p>
            </div>
            
            <div class="mb-3">
                <label class="form-label"><strong>Sucursal:</strong></label>
                <p class="form-control-plaintext"><?= htmlspecialchars($ticket['nombre_sucursal'] ?? 'N/A') ?></p>
            </div>
            
            <div class="mb-3">
                <label class="form-label"><strong>Área/Equipo:</strong></label>
                <p class="form-control-plaintext"><?= htmlspecialchars($ticket['area_equipo']) ?></p>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_nivel_urgencia" class="form-label"><strong>Nivel de Urgencia:</strong></label>
                <div class="row align-items-center">
                    <div class="col-8">
                        <input type="range" class="form-range" id="edit_nivel_urgencia" name="nivel_urgencia" 
                               min="1" max="4" value="<?= $ticket['nivel_urgencia'] ?? 1 ?>" 
                               oninput="updateUrgencyDisplay(this.value)">
                    </div>
                    <div class="col-4">
                        <span id="urgency_display" class="badge fs-6">Nivel <?= $ticket['nivel_urgencia'] ?? 1 ?></span>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="urgency-bar">
                        <div class="urgency-fill urgency-<?= $ticket['nivel_urgencia'] ?? 1 ?>" id="urgency_bar_fill"
                             style="width: <?= ($ticket['nivel_urgencia'] ?? 1) * 25 ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="edit_status" class="form-label"><strong>Estado:</strong></label>
                <select class="form-select" id="edit_status" name="status">
                    <option value="solicitado" <?= $ticket['status'] === 'solicitado' ? 'selected' : '' ?>>Solicitado</option>
                    <option value="clasificado" <?= $ticket['status'] === 'clasificado' ? 'selected' : '' ?>>Clasificado</option>
                    <option value="agendado" <?= $ticket['status'] === 'agendado' ? 'selected' : '' ?>>Agendado</option>
                    <option value="finalizado" <?= $ticket['status'] === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="edit_tipo_caso" class="form-label"><strong>Tipo de Caso:</strong></label>
                <select class="form-select" id="edit_tipo_caso" name="tipo_caso_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($tipos_casos as $tipo): ?>
                        <option value="<?= $tipo['id'] ?>" 
                                <?= $ticket['tipo_caso_id'] == $tipo['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tipo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row">
                <div class="col-6">
                    <div class="mb-3">
                        <label for="edit_fecha_inicio" class="form-label"><strong>Fecha Inicio:</strong></label>
                        <input type="date" class="form-control" id="edit_fecha_inicio" name="fecha_inicio" 
                               value="<?= $ticket['fecha_inicio'] ?>">
                    </div>
                </div>
                <div class="col-6">
                    <div class="mb-3">
                        <label for="edit_fecha_final" class="form-label"><strong>Fecha Final:</strong></label>
                        <input type="date" class="form-control" id="edit_fecha_final" name="fecha_final" 
                               value="<?= $ticket['fecha_final'] ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label class="form-label"><strong>Descripción:</strong></label>
        <div class="form-control" style="min-height: 100px; max-height: 150px; overflow-y: auto;">
            <?= nl2br(htmlspecialchars($ticket['descripcion'])) ?>
        </div>
    </div>
    
    <?php if ($ticket['foto']): ?>
        <div class="mb-3">
            <label class="form-label"><strong>Fotografía:</strong></label>
            <div>
                <img src="uploads/tickets/<?= $ticket['foto'] ?>" alt="Foto del ticket" 
                     class="img-thumbnail" style="max-width: 300px; max-height: 200px;">
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mb-3">
        <label class="form-label"><strong>Creado:</strong></label>
        <p class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></p>
    </div>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Guardar Cambios
        </button>
        <button type="button" class="btn btn-success" onclick="openChatFromModal(<?= $ticket['id'] ?>)">
            <i class="fas fa-comments me-2"></i>Abrir Chat
        </button>
    </div>
</form>

<style>
.urgency-bar {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
}
.urgency-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s ease;
}
.urgency-1 { background: #28a745; }
.urgency-2 { background: #ffc107; }
.urgency-3 { background: #fd7e14; }
.urgency-4 { background: #dc3545; }
</style>

<script>
function updateUrgencyDisplay(value) {
    const display = document.getElementById('urgency_display');
    const fill = document.getElementById('urgency_bar_fill');
    
    display.textContent = 'Nivel ' + value;
    display.className = 'badge fs-6 bg-' + getUrgencyColor(value);
    
    fill.style.width = (value * 25) + '%';
    fill.className = 'urgency-fill urgency-' + value;
}

function getUrgencyColor(level) {
    switch(parseInt(level)) {
        case 1: return 'success';
        case 2: return 'warning';
        case 3: return 'warning';
        case 4: return 'danger';
        default: return 'secondary';
    }
}

function updateTicket(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const ticketId = formData.get('ticket_id') || document.getElementById('ticket_id').value;
    
    $.ajax({
        url: 'ajax/update_ticket.php',
        method: 'POST',
        data: {
            id: ticketId,
            titulo: formData.get('titulo'),
            nivel_urgencia: formData.get('nivel_urgencia'),
            status: formData.get('status'),
            tipo_caso_id: formData.get('tipo_caso_id'),
            fecha_inicio: formData.get('fecha_inicio'),
            fecha_final: formData.get('fecha_final')
        },
        success: function(response) {
            if (response.success) {
                alert('Ticket actualizado exitosamente');
                $('#ticketModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error en la comunicación con el servidor');
        }
    });
}

function openChatFromModal(ticketId) {
    $('#ticketModal').modal('hide');
    window.open('chat.php?ticket_id=' + ticketId + '&emisor=mantenimiento', 
               'chat_' + ticketId, 
               'width=800,height=600,scrollbars=yes,resizable=yes');
}
</script>