<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Ticket.php';

$ticket = new Ticket();
$sucursal = $_GET['sucursal'] ?? '';

$filters = [];
if (!empty($sucursal)) {
    $filters['cod_sucursal'] = $sucursal;
}

$solicitudes = $ticket->getTicketsWithoutDates();

// Filtrar por sucursal si se especificó
if (!empty($sucursal)) {
    $solicitudes = array_filter($solicitudes, function($s) use ($sucursal) {
        return $s['cod_sucursal'] == $sucursal;
    });
}

function getUrgencyClass($nivel) {
    switch($nivel) {
        case 1: return 'urgency-1';
        case 2: return 'urgency-2';
        case 3: return 'urgency-3';
        case 4: return 'urgency-4';
        default: return 'urgency-null';
    }
}

if (empty($solicitudes)):
?>
    <div class="alert alert-info">No hay solicitudes sin programar</div>
<?php else: ?>
    <?php foreach ($solicitudes as $s): ?>
        <div class="ticket-card" 
             draggable="true"
             data-ticket-id="<?php echo $s['id']; ?>"
             data-tipo-formulario="<?php echo htmlspecialchars($s['tipo_formulario']); ?>">
            <div class="urgency-badge <?php echo getUrgencyClass($s['nivel_urgencia']); ?>">
                <?php echo $s['nivel_urgencia'] ?? '-'; ?>
            </div>
            <div class="ticket-title"><?php echo htmlspecialchars($s['titulo']); ?></div>
            <div class="ticket-sucursal"><?php echo htmlspecialchars($s['nombre_sucursal']); ?></div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>