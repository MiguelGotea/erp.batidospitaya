<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';

session_start();

$ticket = new Ticket();

// Obtener semana actual (518)
$semanaActual = 518;
if (isset($_GET['semana'])) {
    $semanaActual = intval($_GET['semana']);
}

// Obtener fechas de la semana
global $db;
$fechasSemana = $db->fetchAll(
    "SELECT DATE(fecha) as fecha, DAYOFWEEK(fecha) as dia_semana 
     FROM FechasSistema 
     WHERE numero_semana = ? 
     AND DAYOFWEEK(fecha) BETWEEN 2 AND 7
     ORDER BY fecha ASC",
    [$semanaActual]
);

// Obtener todas las combinaciones únicas de equipos de trabajo históricamente
$equiposSql = "SELECT DISTINCT GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR ' + ') as equipo
               FROM mtto_tickets_colaboradores
               GROUP BY ticket_id
               ORDER BY equipo";
$equiposRaw = $db->fetchAll($equiposSql);
$equiposTrabajo = array_unique(array_column($equiposRaw, 'equipo'));
sort($equiposTrabajo);

// Obtener solicitudes programadas de la semana
$fechaInicio = $fechasSemana[0]['fecha'] ?? null;
$fechaFin = $fechasSemana[count($fechasSemana) - 1]['fecha'] ?? null;

$solicitudesProgramadas = [];
if ($fechaInicio && $fechaFin) {
    $solicitudesProgramadas = $db->fetchAll(
        "SELECT t.*, s.nombre as nombre_sucursal,
         (SELECT GROUP_CONCAT(DISTINCT tc.tipo_usuario ORDER BY tc.tipo_usuario SEPARATOR ' + ')
          FROM mtto_tickets_colaboradores tc
          WHERE tc.ticket_id = t.id) as equipo_asignado
         FROM mtto_tickets t
         LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
         WHERE DATE(t.fecha_inicio) IS NOT NULL 
         AND DATE(t.fecha_final) IS NOT NULL
         AND (
            (DATE(t.fecha_inicio) <= ? AND DATE(t.fecha_final) >= ?) OR
            (DATE(t.fecha_inicio) BETWEEN ? AND ?) OR
            (DATE(t.fecha_final) BETWEEN ? AND ?)
         )
         ORDER BY s.nombre ASC",
        [$fechaFin, $fechaInicio, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin]
    );
}

// Obtener sucursales para filtro
$sucursales = $ticket->getSucursales();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación de Solicitudes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-header: #0E544C;
            --color-btn: #51B8AC;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
        }
        
        .header-bar {
            background: var(--color-header);
            color: white;
            padding: 1rem 2rem;
            margin-bottom: 1rem;
        }
        
        .btn-primary-custom {
            background: var(--color-btn);
            border: none;
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: #459d92;
            color: white;
        }
        
        .week-navigation {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .calendar-container {
            display: flex;
            gap: 1rem;
            height: calc(100vh - 200px);
        }
        
        .calendar-grid {
            flex: 1;
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .grid-header {
            display: grid;
            grid-template-columns: 200px repeat(6, 1fr);
            background: var(--color-header);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .grid-header > div {
            padding: 1rem;
            text-align: center;
            border-right: 1px solid rgba(255,255,255,0.1);
            font-weight: 500;
        }
        
        .grid-body {
            display: grid;
            grid-template-columns: 200px repeat(6, 1fr);
            min-height: 100px;
        }
        
        .team-label {
            background: #f8f9fa;
            padding: 1rem;
            border-right: 2px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            font-weight: 500;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            position: sticky;
            left: 0;
            z-index: 5;
        }
        
        .day-cell {
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            min-height: 80px;
            padding: 0;
            position: relative;
            overflow: visible;
        }
        
        .day-cell.drop-zone {
            background: #e8f5f3;
        }
        
        .ticket-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            padding: 0.4rem 0.5rem;
            margin: 0.25rem;
            cursor: move;
            position: absolute;
            transition: all 0.2s;
            user-select: none;
            height: 55px;
            box-sizing: border-box;
        }
        
        .ticket-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .ticket-card.dragging {
            opacity: 0.5;
        }
        
        .ticket-title {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 0.15rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 25px;
        }
        
        .ticket-sucursal {
            font-size: 0.7rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .urgency-badge {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            width: 20px;
            height: 20px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
            color: white;
        }
        
        .btn-desprogramar {
            position: absolute;
            top: 0.25rem;
            right: 2rem;
            width: 20px;
            height: 20px;
            border-radius: 3px;
            background: #dc3545;
            color: white;
            border: none;
            padding: 0;
            font-size: 0.7rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .ticket-card:hover .btn-desprogramar {
            opacity: 1;
        }
        
        .btn-desprogramar:hover {
            background: #c82333;
        }
        
        .urgency-1 { background: #28a745; }
        .urgency-2 { background: #ffc107; color: #000; }
        .urgency-3 { background: #fd7e14; }
        .urgency-4 { background: #dc3545; }
        .urgency-null { background: #8b8b8b; }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            right: -450px;
            top: 0;
            width: 450px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            transition: right 0.3s;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.open {
            right: 0;
        }
        
        .sidebar-header {
            background: var(--color-header);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sidebar-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .sidebar-filter {
            margin-bottom: 1rem;
        }
        
        .ticket-card-extended {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            z-index: 2;
            pointer-events: all;
        }
        
        .resize-handle {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 12px;
            cursor: ew-resize;
            background: rgba(81, 184, 172, 0.3);
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }
        
        .ticket-card:hover .resize-handle {
            opacity: 1;
        }
        
        .resizing {
            cursor: ew-resize !important;
        }
        
        .resizing * {
            cursor: ew-resize !important;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <h4 class="mb-0">Programación de Solicitudes - Semana <?php echo $semanaActual; ?></h4>
    </div>

    <div class="container-fluid px-4">
        <div class="week-navigation">
            <button class="btn btn-primary-custom" onclick="cambiarSemana(<?php echo $semanaActual - 1; ?>)">
                <i class="fas fa-chevron-left"></i> Semana Anterior
            </button>
            <span class="fw-bold">Semana <?php echo $semanaActual; ?></span>
            <button class="btn btn-primary-custom" onclick="cambiarSemana(<?php echo $semanaActual + 1; ?>)">
                Semana Siguiente <i class="fas fa-chevron-right"></i>
            </button>
            <button class="btn btn-primary-custom" onclick="toggleSidebar()">
                <i class="fas fa-list"></i> Solicitudes por Programar
            </button>
        </div>

        <div class="calendar-container">
            <div class="calendar-grid">
                <div class="grid-header">
                    <div>Equipo de Trabajo</div>
                    <?php foreach ($fechasSemana as $fecha): 
                        $dias = ['', 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                        $fechaObj = new DateTime($fecha['fecha']);
                    ?>
                        <div>
                            <?php echo $dias[$fecha['dia_semana']]; ?><br>
                            <small><?php echo $fechaObj->format('d/m/Y'); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="calendar-body">
                    <?php foreach ($equiposTrabajo as $equipo): ?>
                        <div class="grid-body" data-equipo="<?php echo htmlspecialchars($equipo); ?>">
                            <div class="team-label"><?php echo htmlspecialchars($equipo); ?></div>
                            <?php foreach ($fechasSemana as $fecha): ?>
                                <div class="day-cell" 
                                     data-fecha="<?php echo $fecha['fecha']; ?>"
                                     data-equipo="<?php echo htmlspecialchars($equipo); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Grupo especial: Cambio de Equipos -->
                    <div class="grid-body" data-equipo="Cambio de Equipos" data-tipo="cambio_equipos">
                        <div class="team-label">Cambio de Equipos</div>
                        <?php foreach ($fechasSemana as $fecha): ?>
                            <div class="day-cell" 
                                 data-fecha="<?php echo $fecha['fecha']; ?>"
                                 data-equipo="Cambio de Equipos"
                                 data-tipo="cambio_equipos">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0">Solicitudes Sin Programar</h5>
            <button class="btn btn-sm btn-light" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="sidebar-body">
            <div class="sidebar-filter">
                <select class="form-select" id="filtroSucursal" onchange="cargarSolicitudesSinProgramar()">
                    <option value="">Todas las sucursales</option>
                    <?php foreach ($sucursales as $suc): ?>
                        <option value="<?php echo htmlspecialchars($suc['cod_sucursal']); ?>">
                            <?php echo htmlspecialchars($suc['nombre_sucursal']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="solicitudes-sin-programar"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let solicitudesProgramadas = <?php echo json_encode($solicitudesProgramadas, JSON_UNESCAPED_UNICODE); ?>;
        let fechasSemana = <?php echo json_encode($fechasSemana, JSON_UNESCAPED_UNICODE); ?>;
        
        function cambiarSemana(semana) {
            window.location.href = '?semana=' + semana;
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                cargarSolicitudesSinProgramar();
            }
        }
        
        function cargarSolicitudesSinProgramar() {
            const sucursal = $('#filtroSucursal').val();
            $.ajax({
                url: 'ajax/get_solicitudes_sin_programar.php',
                method: 'GET',
                data: { sucursal: sucursal },
                success: function(response) {
                    $('#solicitudes-sin-programar').html(response);
                    initDraggableSidebar();
                }
            });
        }
        
        function renderizarSolicitudes() {
            // Limpiar celdas
            $('.day-cell').empty().css('min-height', '80px');
            
            // Obtener ancho de celda una sola vez
            const primeraCelda = document.querySelector('.day-cell');
            const anchoBase = primeraCelda ? primeraCelda.offsetWidth : 150;
            
            // Agrupar tickets por equipo
            const ticketsPorEquipo = {};
            
            solicitudesProgramadas.forEach(ticket => {
                const equipoAsignado = ticket.equipo_asignado || 'Cambio de Equipos';
                if (!ticketsPorEquipo[equipoAsignado]) {
                    ticketsPorEquipo[equipoAsignado] = [];
                }
                ticketsPorEquipo[equipoAsignado].push(ticket);
            });
            
            // Procesar cada equipo
            Object.keys(ticketsPorEquipo).forEach(equipo => {
                const tickets = ticketsPorEquipo[equipo];
                
                // Array de filas: cada elemento es un array de 6 elementos (uno por día)
                // true = ocupado, false = libre
                const filas = [];
                
                // Crear estructura para trackear posiciones
                const posiciones = []; // {ticket, fila, columnaInicio, dias}
                
                tickets.forEach(ticket => {
                    const fechaInicio = new Date(ticket.fecha_inicio + 'T00:00:00');
                    const fechaFinal = new Date(ticket.fecha_final + 'T00:00:00');
                    
                    // Encontrar índice de fecha en la semana
                    const indiceFechaInicio = fechasSemana.findIndex(f => {
                        const fechaSemana = new Date(f.fecha + 'T00:00:00');
                        return fechaSemana.getTime() === fechaInicio.getTime();
                    });
                    
                    if (indiceFechaInicio === -1) return;
                    
                    // Calcular días que abarca
                    const diffTime = fechaFinal - fechaInicio;
                    const diffDays = Math.min(Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1, 6 - indiceFechaInicio);
                    
                    // Encontrar la primera fila donde cabe
                    let filaEncontrada = -1;
                    for (let f = 0; f < filas.length; f++) {
                        let cabe = true;
                        for (let d = 0; d < diffDays; d++) {
                            if (indiceFechaInicio + d < 6 && filas[f][indiceFechaInicio + d]) {
                                cabe = false;
                                break;
                            }
                        }
                        if (cabe) {
                            filaEncontrada = f;
                            break;
                        }
                    }
                    
                    // Si no cabe en ninguna fila, crear una nueva
                    if (filaEncontrada === -1) {
                        filaEncontrada = filas.length;
                        filas.push([false, false, false, false, false, false]);
                    }
                    
                    // Marcar los días como ocupados
                    for (let d = 0; d < diffDays; d++) {
                        if (indiceFechaInicio + d < 6) {
                            filas[filaEncontrada][indiceFechaInicio + d] = true;
                        }
                    }
                    
                    // Guardar posición
                    posiciones.push({
                        ticket: ticket,
                        fila: filaEncontrada,
                        columnaInicio: indiceFechaInicio,
                        dias: diffDays
                    });
                });
                
                // Ahora crear las tarjetas con las posiciones calculadas
                posiciones.forEach(pos => {
                    const ticket = pos.ticket;
                    const urgencyClass = ticket.nivel_urgencia ? `urgency-${ticket.nivel_urgencia}` : 'urgency-null';
                    
                    // Encontrar la celda inicial
                    const primeraFecha = fechasSemana[pos.columnaInicio];
                    const celda = document.querySelector(
                        `.day-cell[data-fecha="${primeraFecha.fecha}"][data-equipo="${equipo}"]`
                    );
                    
                    if (!celda) return;
                    
                    const card = document.createElement('div');
                    card.className = 'ticket-card';
                    card.draggable = true;
                    card.dataset.ticketId = ticket.id;
                    card.dataset.tipoFormulario = ticket.tipo_formulario;
                    card.dataset.fechaInicio = ticket.fecha_inicio;
                    card.dataset.fechaFinal = ticket.fecha_final;
                    card.dataset.dias = pos.dias;
                    
                    card.innerHTML = `
                        <button class="btn-desprogramar" onclick="desprogramarTicket(${ticket.id}, event)">×</button>
                        <div class="urgency-badge ${urgencyClass}">${ticket.nivel_urgencia || '-'}</div>
                        <div class="ticket-title">${ticket.titulo}</div>
                        <div class="ticket-sucursal">${ticket.nombre_sucursal}</div>
                        <div class="resize-handle"></div>
                    `;
                    
                    // Calcular posición
                    const topPos = (pos.fila * 60) + 5; // 60px por fila + 5px margen
                    const leftPos = 5; // margen izquierdo
                    
                    // Calcular ancho según días
                    let anchoCard;
                    if (pos.dias === 1) {
                        anchoCard = anchoBase - 10; // restar márgenes
                    } else {
                        // Para múltiples días: ancho = (anchoBase * días) + (gaps * (días-1)) - márgenes
                        anchoCard = (anchoBase * pos.dias) + (1 * (pos.dias - 1)) - 10;
                    }
                    
                    card.style.top = topPos + 'px';
                    card.style.left = leftPos + 'px';
                    card.style.width = anchoCard + 'px';
                    card.style.zIndex = pos.dias > 1 ? '3' : '2';
                    
                    celda.appendChild(card);
                    
                    card.addEventListener('click', function(e) {
                        if (!e.target.classList.contains('resize-handle') && 
                            !e.target.classList.contains('btn-desprogramar') && 
                            !resizing) {
                            mostrarDetallesTicket(ticket.id);
                        }
                    });
                });
                
                // Ajustar altura de todas las celdas del equipo
                const alturaMinima = (filas.length * 60) + 20;
                const celdasEquipo = document.querySelectorAll(`.day-cell[data-equipo="${equipo}"]`);
                celdasEquipo.forEach(celda => {
                    celda.style.minHeight = Math.max(80, alturaMinima) + 'px';
                });
            });
            
            initDraggableTickets();
            initResizable();
        }
        
        function initDraggableTickets() {
            const tickets = document.querySelectorAll('.day-cell .ticket-card');
            tickets.forEach(ticket => {
                ticket.addEventListener('dragstart', handleDragStart);
                ticket.addEventListener('dragend', handleDragEnd);
            });
            
            const cells = document.querySelectorAll('.day-cell');
            cells.forEach(cell => {
                cell.addEventListener('dragover', handleDragOver);
                cell.addEventListener('drop', handleDrop);
                cell.addEventListener('dragleave', handleDragLeave);
            });
        }
        
        function initDraggableSidebar() {
            const tickets = document.querySelectorAll('#solicitudes-sin-programar .ticket-card');
            tickets.forEach(ticket => {
                ticket.addEventListener('dragstart', handleDragStart);
                ticket.addEventListener('dragend', handleDragEnd);
            });
        }
        
        function initResizable() {
            const handles = document.querySelectorAll('.resize-handle');
            handles.forEach(handle => {
                handle.addEventListener('mousedown', startResize);
            });
        }
        
        let resizing = null;
        function startResize(e) {
            e.preventDefault();
            e.stopPropagation();
            
            resizing = {
                card: e.target.closest('.ticket-card'),
                startX: e.clientX,
                startWidth: e.target.closest('.ticket-card').offsetWidth,
                startDias: parseInt(e.target.closest('.ticket-card').dataset.dias)
            };
            
            resizing.card.draggable = false;
            document.body.classList.add('resizing');
            
            document.addEventListener('mousemove', doResize);
            document.addEventListener('mouseup', stopResize);
        }
        
        function doResize(e) {
            if (!resizing) return;
            
            const diff = e.clientX - resizing.startX;
            const celda = resizing.card.closest('.day-cell');
            const anchoBase = celda.offsetWidth;
            
            // Calcular cuántos días debería abarcar según el ancho actual
            const anchoActual = resizing.startWidth + diff;
            let newDays = 1;
            
            for (let d = 1; d <= 6; d++) {
                const anchoEsperado = (anchoBase * d) + (1 * (d - 1)) - 10;
                if (anchoActual >= anchoEsperado - (anchoBase / 3)) {
                    newDays = d;
                }
            }
            
            // Calcular el ancho exacto para ese número de días
            const anchoFinal = (anchoBase * newDays) + (1 * (newDays - 1)) - 10;
            
            resizing.card.style.width = anchoFinal + 'px';
            resizing.card.dataset.dias = newDays;
        }
        
        function stopResize(e) {
            if (!resizing) return;
            
            const ticketId = resizing.card.dataset.ticketId;
            const dias = parseInt(resizing.card.dataset.dias);
            const fechaInicio = new Date(resizing.card.dataset.fechaInicio + 'T00:00:00');
            const fechaFinal = new Date(fechaInicio);
            fechaFinal.setDate(fechaFinal.getDate() + dias - 1);
            
            const fechaInicioStr = fechaInicio.toISOString().split('T')[0];
            const fechaFinalStr = fechaFinal.toISOString().split('T')[0];
            
            actualizarFechas(ticketId, fechaInicioStr, fechaFinalStr);
            
            resizing.card.draggable = true;
            document.body.classList.remove('resizing');
            
            document.removeEventListener('mousemove', doResize);
            document.removeEventListener('mouseup', stopResize);
            resizing = null;
        }
        
        let draggedElement = null;
        function handleDragStart(e) {
            if (resizing) {
                e.preventDefault();
                return;
            }
            draggedElement = e.target;
            e.target.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
        
        function handleDragEnd(e) {
            e.target.classList.remove('dragging');
            draggedElement = null;
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            e.currentTarget.classList.add('drop-zone');
        }
        
        function handleDragLeave(e) {
            e.currentTarget.classList.remove('drop-zone');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('drop-zone');
            
            if (!draggedElement) return;
            
            const ticketId = draggedElement.dataset.ticketId;
            const tipoFormulario = draggedElement.dataset.tipoFormulario;
            const targetCell = e.currentTarget;
            const targetEquipo = targetCell.dataset.equipo;
            const targetTipo = targetCell.dataset.tipo;
            const targetFecha = targetCell.dataset.fecha;
            
            // Validar que no se mezclen tipos
            if (tipoFormulario === 'cambio_equipos' && targetTipo !== 'cambio_equipos') {
                alert('Las solicitudes de Cambio de Equipos solo pueden ir en su grupo específico');
                return;
            }
            if (tipoFormulario === 'mantenimiento_general' && targetTipo === 'cambio_equipos') {
                alert('Las solicitudes de Mantenimiento General no pueden ir en Cambio de Equipos');
                return;
            }
            
            // Si viene del sidebar, asignar
            if (draggedElement.closest('#solicitudes-sin-programar')) {
                asignarTicket(ticketId, targetEquipo, targetFecha);
            } else {
                // Mover entre grupos o fechas
                moverTicket(ticketId, targetEquipo, targetFecha);
            }
        }
        
        function asignarTicket(ticketId, equipo, fecha) {
            $.ajax({
                url: 'ajax/asignar_ticket.php',
                method: 'POST',
                data: {
                    ticket_id: ticketId,
                    equipo: equipo,
                    fecha_inicio: fecha,
                    fecha_final: fecha
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error al asignar: ' + response.message);
                    }
                },
                dataType: 'json'
            });
        }
        
        function moverTicket(ticketId, equipo, fecha) {
            const card = document.querySelector(`.ticket-card[data-ticket-id="${ticketId}"]`);
            const dias = parseInt(card.dataset.dias) || 1;
            const fechaInicio = new Date(fecha);
            const fechaFinal = new Date(fechaInicio);
            fechaFinal.setDate(fechaFinal.getDate() + dias - 1);
            
            $.ajax({
                url: 'ajax/mover_ticket.php',
                method: 'POST',
                data: {
                    ticket_id: ticketId,
                    equipo: equipo,
                    fecha_inicio: fechaInicio.toISOString().split('T')[0],
                    fecha_final: fechaFinal.toISOString().split('T')[0]
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error al mover: ' + response.message);
                    }
                },
                dataType: 'json'
            });
        }
        
        function desprogramarTicket(ticketId, event) {
            event.stopPropagation();
            
            if (!confirm('¿Desea desprogramar esta solicitud?')) {
                return;
            }
            
            $.ajax({
                url: 'ajax/desprogramar_ticket.php',
                method: 'POST',
                data: { ticket_id: ticketId },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error al desprogramar: ' + response.message);
                    }
                },
                dataType: 'json'
            });
        }
        
        function actualizarFechas(ticketId, fechaInicio, fechaFinal) {
            $.ajax({
                url: 'ajax/actualizar_fechas.php',
                method: 'POST',
                data: {
                    ticket_id: ticketId,
                    fecha_inicio: fechaInicio,
                    fecha_final: fechaFinal
                },
                success: function(response) {
                    if (!response.success) {
                        alert('Error al actualizar fechas: ' + response.message);
                    }
                },
                dataType: 'json'
            });
        }
        
        function mostrarDetallesTicket(ticketId) {
            $.ajax({
                url: 'ajax/get_ticket_details.php',
                method: 'GET',
                data: { id: ticketId },
                success: function(response) {
                    const modal = $('<div class="modal fade"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">' + response + '</div></div></div></div>');
                    $('body').append(modal);
                    
                    setTimeout(function() {
                        const hiddenInput = document.getElementById('edit_nivel_urgencia');
                        if (hiddenInput && typeof window.currentUrgency !== 'undefined') {
                            const newUrgency = hiddenInput.value ? parseInt(hiddenInput.value) : null;
                            window.currentUrgency = newUrgency;
                        }
                        
                        if (typeof initUrgencyControls === 'function') {
                            initUrgencyControls();
                        }
                    }, 0);
                    
                    modal.modal('show');
                    
                    modal.on('hidden.bs.modal', function() { 
                        modal.remove(); 
                    });
                }
            });
        }
        
        // Inicializar al cargar
        $(document).ready(function() {
            renderizarSolicitudes();
        });
    </script>
</body>
</html>