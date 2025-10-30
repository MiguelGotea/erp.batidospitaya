<?php
require_once '../models/Ticket.php';

if (!isset($_GET['ticket_id'])) {
    die('ID requerido');
}

$ticket = new Ticket();
$ticket_id = $_GET['ticket_id'];
$colaboradoresDisponibles = $ticket->getColaboradoresDisponibles();
$colaboradoresAsignados = $ticket->getColaboradores($ticket_id);
$asignadosIds = array_column($colaboradoresAsignados, 'cod_operario');
?>

<div class="modal-header">
    <h5 class="modal-title">
        <i class="fas fa-users me-2"></i>
        Gestionar Colaboradores
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
    <form id="formColaboradores">
        <input type="hidden" name="ticket_id" value="<?= $ticket_id ?>">
        
        <div class="mb-3">
            <label class="form-label"><strong>Colaboradores asignados:</strong></label>
            <?php if (empty($colaboradoresAsignados)): ?>
                <p class="text-muted">No hay colaboradores asignados</p>
            <?php else: ?>
                <div class="list-group mb-3">
                    <?php foreach ($colaboradoresAsignados as $col): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-user me-2"></i>
                                <?= htmlspecialchars($col['Nombre'] . ' ' . ($col['Apellido'] ?? '')) ?>
                            </span>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="removerColaborador(<?= $ticket_id ?>, <?= $col['cod_operario'] ?>, this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><strong>Agregar colaborador:</strong></label>
            <select class="form-select" id="nuevoColaborador">
                <option value="">Seleccionar...</option>
                <?php foreach ($colaboradoresDisponibles as $col): ?>
                    <?php if (!in_array($col['CodOperario'], $asignadosIds)): ?>
                        <option value="<?= $col['CodOperario'] ?>">
                            <?= htmlspecialchars($col['Nombre'] . ' ' . ($col['Apellido'] ?? '')) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <button type="button" class="btn btn-primary" onclick="agregarColaborador(<?= $ticket_id ?>)">
        <i class="fas fa-plus me-2"></i>Agregar
    </button>
</div>

<script>
function agregarColaborador(ticketId) {
    const select = document.getElementById('nuevoColaborador');
    const codOperario = select.value;
    
    if (!codOperario) {
        alert('Selecciona un colaborador');
        return;
    }
    
    $.ajax({
        url: 'ajax/agregar_colaborador.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            cod_operario: codOperario
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#modalColaboradores').modal('hide');
                refrescarCalendarioYSidebar();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function removerColaborador(ticketId, codOperario, btn) {
    if (!confirm('¿Remover este colaborador?')) return;
    
    $.ajax({
        url: 'ajax/remover_colaborador.php',
        method: 'POST',
        data: {
            ticket_id: ticketId,
            cod_operario: codOperario
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $(btn).closest('.list-group-item').fadeOut(300, function() {
                    $(this).remove();
                    if ($('.list-group-item').length === 0) {
                        $('.list-group').replaceWith('<p class="text-muted">No hay colaboradores asignados</p>');
                    }
                });
                refrescarCalendarioYSidebar();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}
</script>