<?php
require_once '../models/Ticket.php';

if (!isset($_GET['id'])) {
    die('ID de ticket requerido');
}

$ticket_model = new Ticket();
$ticket = $ticket_model->getById($_GET['id']);
$fotos = $ticket_model->getFotos($_GET['id']);

if (!$ticket) {
    die('Ticket no encontrado');
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label"><strong>Código:</strong></label>
            <p class="form-control-plaintext"><?= htmlspecialchars($ticket['codigo']) ?></p>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><strong>Título:</strong></label>
            <p class="form-control-plaintext"><?= htmlspecialchars($ticket['titulo']) ?></p>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><strong>Tipo:</strong></label>
            <p class="form-control-plaintext">
                <span class="badge <?= $ticket['tipo_formulario'] === 'mantenimiento_general' ? 'bg-primary' : 'bg-info' ?>">
                    <?= $ticket['tipo_formulario'] === 'mantenimiento_general' ? 'Mantenimiento General' : 'Cambio de Equipos' ?>
                </span>
            </p>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><strong>Área/Equipo:</strong></label>
            <p class="form-control-plaintext"><?= htmlspecialchars($ticket['area_equipo']) ?></p>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><strong>Estado Actual:</strong></label>
            <p class="form-control-plaintext">
                <span class="status-badge status-<?= $ticket['status'] ?>">
                    <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                </span>
            </p>
        </div>
    </div>
    
    <div class="col-md-6">
        <?php if ($ticket['nivel_urgencia']): ?>
            <div class="mb-3">
                <label class="form-label"><strong>Nivel de Urgencia:</strong></label>
                <div class="mt-2">
                    <div class="urgency-bar">
                        <div class="urgency-fill urgency-<?= $ticket['nivel_urgencia'] ?>"
                             style="width: <?= $ticket['nivel_urgencia'] * 25 ?>%"></div>
                    </div>
                    <small>Nivel <?= $ticket['nivel_urgencia'] ?> de 4</small>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['tipo_caso_nombre']): ?>
            <div class="mb-3">
                <label class="form-label"><strong>Categoría:</strong></label>
                <p class="form-control-plaintext">
                    <span class="badge bg-secondary"><?= htmlspecialchars($ticket['tipo_caso_nombre']) ?></span>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ($ticket['fecha_inicio'] && $ticket['fecha_final']): ?>
            <div class="mb-3">
                <label class="form-label"><strong>Fecha Programada:</strong></label>
                <p class="form-control-plaintext">
                    <i class="fas fa-calendar me-2"></i>
                    <?= date('d/m/Y', strtotime($ticket['fecha_inicio'])) ?> 
                    al 
                    <?= date('d/m/Y', strtotime($ticket['fecha_final'])) ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="mb-3">
            <label class="form-label"><strong>Creado:</strong></label>
            <p class="form-control-plaintext">
                <i class="fas fa-clock me-2"></i>
                <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
            </p>
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label"><strong>Descripción:</strong></label>
    <div class="card">
        <div class="card-body">
            <?= nl2br(htmlspecialchars($ticket['descripcion'])) ?>
        </div>
    </div>
</div>

<?php if (!empty($fotos)): ?>
    <div class="mb-3">
        <label class="form-label"><strong>Fotografías (<?= count($fotos) ?>):</strong></label>
        <div class="photos-grid">
            <?php foreach ($fotos as $index => $foto): ?>
                <div class="photo-item">
                    <img src="uploads/tickets/<?= $foto['foto'] ?>" alt="Foto <?= $index + 1 ?>" 
                         class="img-thumbnail" 
                         style="max-width: 200px; max-height: 150px; object-fit: cover; cursor: pointer;"
                         onclick="showPhotoFullscreen('uploads/tickets/<?= $foto['foto'] ?>', <?= $index + 1 ?>, <?= count($fotos) ?>)">
                    <small class="d-block text-center text-muted mt-1">Foto <?= $index + 1 ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <small class="text-muted d-block mt-2">
            <i class="fas fa-info-circle me-1"></i>
            Haz clic en cualquier imagen para verla en tamaño completo
        </small>
    </div>
<?php endif; ?>

<!-- Estado visual del proceso -->
<div class="mb-3">
    <label class="form-label"><strong>Progreso:</strong></label>
    <div class="progress-steps">
        <div class="d-flex justify-content-between">
            <div class="step <?= in_array($ticket['status'], ['solicitado', 'clasificado', 'agendado', 'finalizado']) ? 'active' : '' ?>">
                <div class="step-circle">1</div>
                <div class="step-label">Solicitado</div>
            </div>
            <div class="step <?= in_array($ticket['status'], ['clasificado', 'agendado', 'finalizado']) ? 'active' : '' ?>">
                <div class="step-circle">2</div>
                <div class="step-label">Clasificado</div>
            </div>
            <div class="step <?= in_array($ticket['status'], ['agendado', 'finalizado']) ? 'active' : '' ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Agendado</div>
            </div>
            <div class="step <?= $ticket['status'] === 'finalizado' ? 'active' : '' ?>">
                <div class="step-circle">4</div>
                <div class="step-label">Finalizado</div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <button type="button" class="btn btn-success" onclick="openChatFromModal(<?= $ticket['id'] ?>)">
        <i class="fas fa-comments me-2"></i>Abrir Chat
    </button>
</div>

<style>
.photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.photo-item {
    text-align: center;
}

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

.status-badge {
    font-size: 0.8em;
    padding: 0.4em 0.8em;
    border-radius: 20px;
}
.status-solicitado { background: #6c757d; color: white; }
.status-clasificado { background: #0dcaf0; color: white; }
.status-agendado { background: #fd7e14; color: white; }
.status-finalizado { background: #198754; color: white; }

.progress-steps {
    margin: 20px 0;
}
.step {
    text-align: center;
    position: relative;
}
.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin: 0 auto 10px;
    transition: all 0.3s ease;
}
.step.active .step-circle {
    background: #007bff;
    color: white;
}
.step-label {
    font-size: 0.9em;
    color: #6c757d;
}
.step.active .step-label {
    color: #007bff;
    font-weight: bold;
}
.step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 20px;
    left: 50%;
    right: -50%;
    height: 2px;
    background: #e9ecef;
    z-index: -1;
}
.step.active:not(:last-child)::after {
    background: #007bff;
}
</style>

<script>
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

function openChatFromModal(ticketId) {
    $('#ticketModal').modal('hide');
    const url = `chat.php?ticket_id=${ticketId}&emisor=solicitante`;
    window.location.href = url;
}
</script>