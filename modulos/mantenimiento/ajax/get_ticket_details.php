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

// Determinar etiqueta de área según tipo
$labelArea = ($ticket['tipo_formulario'] === 'cambio_equipos') ? 'Equipo' : 'Área';
$encabezado = ($ticket['tipo_formulario'] === 'cambio_equipos') ? 'Solicitud de Equipos' : 'Mantenimiento General';

// Colores de urgencia
$coloresUrgencia = [
    0 => '#8b8b8bff',
    1 => '#28a745',
    2 => '#ffc107',
    3 => '#fd7e14',
    4 => '#dc3545'
];

$textosUrgencia = [
    0 => 'No Clasificado',
    1 => 'No Urgente',
    2 => 'Medio',
    3 => 'Urgente',
    4 => 'Crítico'
];
?>

<style>
.modal-detalles-ticket * {
    box-sizing: border-box;
}
.modal-detalles-ticket .modal-header {
    background-color: #0E544C;
    color: white;
    padding: 1rem;
}
.modal-detalles-ticket .form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
}
.modal-detalles-ticket .form-control,
.modal-detalles-ticket .form-select {
    border: 1px solid #dee2e6;
    border-radius: 4px;
}
.modal-detalles-ticket .form-control:focus,
.modal-detalles-ticket .form-select:focus {
    border-color: #51B8AC;
    box-shadow: 0 0 0 0.2rem rgba(81, 184, 172, 0.25);
}

/* Urgencia estilo compacto */
.urgencia-selector-compacto {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 120px;
}
.urgencia-selector-compacto:hover {
    opacity: 0.85;
    transform: scale(1.02);
}
.urgencia-opciones {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.urgencia-opcion {
    padding: 0.6rem;
    border-radius: 4px;
    color: white;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.urgencia-opcion:hover {
    opacity: 0.85;
    transform: translateX(4px);
}
.urgencia-opcion.selected {
    border: 3px solid white;
    box-shadow: 0 0 0 3px rgba(255,255,255,0.3);
}

/* Carousel de fotos compacto */
.fotos-carousel {
    position: relative;
    max-width: 100%;
    margin: 1rem 0;
}
.fotos-carousel-inner {
    position: relative;
    width: 100%;
    height: 300px;
    overflow: hidden;
    border-radius: 8px;
}
.fotos-carousel-item {
    display: none;
    width: 100%;
    height: 100%;
}
.fotos-carousel-item.active {
    display: block;
}
.fotos-carousel-item img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background-color: #f8f9fa;
}
.fotos-carousel-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0,0,0,0.5);
    color: white;
    border: none;
    padding: 0.75rem;
    cursor: pointer;
    border-radius: 4px;
    z-index: 10;
}
.fotos-carousel-control:hover {
    background-color: rgba(0,0,0,0.7);
}
.fotos-carousel-control.prev {
    left: 10px;
}
.fotos-carousel-control.next {
    right: 10px;
}
.fotos-indicators {
    text-align: center;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #666;
}
.foto-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

/* Materiales */
.materiales-container {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    background-color: #f8f9fa;
}
.material-row {
    display: grid;
    grid-template-columns: 2fr 2fr 1.5fr auto;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    align-items: center;
}
.material-search {
    margin-bottom: 1rem;
}
.materiales-lista {
    max-height: 300px;
    overflow-y: auto;
}

/* Colaboradores */
.colaboradores-container {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
}
.colaborador-row {
    display: grid;
    grid-template-columns: 1.5fr 2fr auto;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    align-items: center;
}

/* Botones */
.btn-primary-custom {
    background-color: #51B8AC;
    border-color: #51B8AC;
    color: white;
}
.btn-primary-custom:hover {
    background-color: #0E544C;
    border-color: #0E544C;
}
</style>

<div class="modal-detalles-ticket">
    <div class="modal-header">
        <h5 class="modal-title"><?= $encabezado ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    
    <div class="modal-body">
        <form id="formDetallesTicket">
            <input type="hidden" id="ticket_id" value="<?= $ticket['id'] ?>">
            <input type="hidden" id="nivel_urgencia_hidden" value="<?= $ticket['nivel_urgencia'] ?? 0 ?>">
            
            <!-- Información básica -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Sucursal:</label>
                    <p class="form-control-plaintext"><?= htmlspecialchars($ticket['nombre_sucursal'] ?? 'N/A') ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Solicitante:</label>
                    <p class="form-control-plaintext"><?= htmlspecialchars($ticket['nombre_operario'] ?? 'N/A') ?></p>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="titulo" class="form-label">Título:</label>
                    <input type="text" class="form-control" id="titulo" 
                           value="<?= htmlspecialchars($ticket['titulo']) ?>" 
                           <?= !$puedeEditar ? 'readonly' : '' ?> required>
                </div>
                <div class="col-md-6">
                    <label for="area_equipo" class="form-label"><?= $labelArea ?>:</label>
                    <input type="text" class="form-control" id="area_equipo" 
                           value="<?= htmlspecialchars($ticket['area_equipo']) ?>" 
                           <?= !$puedeEditar ? 'readonly' : '' ?> required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Creado:</label>
                    <p class="form-control-plaintext"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nivel de Urgencia:</label>
                    <?php if ($puedeEditar): ?>
                        <div id="urgencia-selector"></div>
                    <?php else: ?>
                        <?php 
                        $nivelActual = $ticket['nivel_urgencia'] ?? 0;
                        $colorActual = $coloresUrgencia[$nivelActual];
                        $textoActual = $textosUrgencia[$nivelActual];
                        ?>
                        <div class="urgencia-selector-compacto" style="background-color: <?= $colorActual ?>; cursor: default;">
                            <?= $textoActual ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción:</label>
                <textarea class="form-control" id="descripcion" rows="4" 
                          <?= !$puedeEditar ? 'readonly' : '' ?> required><?= htmlspecialchars($ticket['descripcion']) ?></textarea>
            </div>
            
            <!-- Fotos -->
            <div class="mb-3">
                <label class="form-label">Fotografías (<?= count($fotos) ?>):</label>
                <div id="fotosCarousel"></div>
            </div>
            
            <!-- Materiales (solo para mantenimiento) -->
            <?php if ($ticket['tipo_formulario'] === 'mantenimiento_general'): ?>
            <div class="mb-3">
                <label class="form-label">Materiales Utilizados:</label>
                <div class="materiales-container" id="materialesContainer"></div>
            </div>
            <?php endif; ?>
            
            <!-- Colaboradores Asignados -->
            <div class="mb-3">
                <label class="form-label">Colaboradores Asignados:</label>
                <div class="colaboradores-container" id="colaboradoresContainer"></div>
            </div>
        </form>
    </div>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <?php if ($puedeEditar): ?>
        <button type="button" class="btn btn-primary-custom" onclick="guardarCambios()">
            <i class="bi bi-save"></i> Guardar Cambios
        </button>
        <?php endif; ?>
    </div>
</div>

<script src="js/detalles_ticket.js"></script>
<script>
const ticketData = {
    id: <?= $ticket['id'] ?>,
    tipo_formulario: '<?= $ticket['tipo_formulario'] ?>',
    puedeEditar: <?= $puedeEditar ? 'true' : 'false' ?>,
    nivelUrgencia: <?= $ticket['nivel_urgencia'] ?? 0 ?>,
    fotos: <?= json_encode($fotos, JSON_UNESCAPED_UNICODE) ?>,
    coloresUrgencia: <?= json_encode($coloresUrgencia) ?>,
    textosUrgencia: <?= json_encode($textosUrgencia) ?>
};

// Inicializar componentes
document.addEventListener('DOMContentLoaded', function() {
    initDetallesTicket(ticketData);
});
</script>