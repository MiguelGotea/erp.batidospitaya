<?php
// programacion_solicitudes.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';

$ticket = new Ticket();
$sucursales = $ticket->getSucursales();

// Obtener semana actual (518)
$semana_actual = 518;
if (isset($_GET['semana'])) {
    $semana_actual = intval($_GET['semana']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación Semanal - Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-primary: #0E544C;
            --color-secondary: #51B8AC;
            --urgency-1: #28a745;
            --urgency-2: #ffc107;
            --urgency-3: #fd7e14;
            --urgency-4: #dc3545;
            --urgency-none: #8b8b8b;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            font-size: 14px;
        }
        
        .header-bar {
            background-color: var(--color-primary);
            color: white;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background-color: var(--color-secondary);
            border-color: var(--color-secondary);
        }
        
        .btn-primary:hover {
            background-color: #459e93;
            border-color: #459e93;
        }
        
        .week-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .schedule-container {
            overflow-x: auto;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: 180px repeat(6, 1fr);
            min-width: 1200px;
        }
        
        .schedule-header {
            background-color: var(--color-primary);
            color: white;
            padding: 0.6rem;
            font-weight: 600;
            text-align: center;
            border: 1px solid #0a3d36;
            font-size: 0.85rem;
        }
        
        .schedule-header.team-label {
            text-align: left;
            padding-left: 1rem;
        }
        
        .team-cell {
            background-color: #e9ecef;
            padding: 0.6rem 1rem;
            font-weight: 600;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            font-size: 0.85rem;
        }
        
        .day-cell {
            border: 1px solid #dee2e6;
            min-height: 80px;
            position: relative;
            padding: 5px;
        }
        
        .ticket-card {
            position: absolute;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.15);
            cursor: move;
            height: 55px;
            box-sizing: border-box;
            padding: 0.4rem 0.5rem;
            margin: 0.25rem;
            border-left: 3px solid var(--color-secondary);
            overflow: hidden;
        }
        
        .ticket-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            z-index: 10;
        }
        
        .ticket-title {
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 0.2rem;
            padding-right: 25px;
        }
        
        .ticket-sucursal {
            font-size: 0.7rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 25px;
        }
        
        .urgency-badge {
            position: absolute;
            bottom: 0.25rem;
            right: 0.25rem;
            width: 22px;
            height: 22px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: white;
        }
        
        .btn-unschedule {
            position: absolute;
            top: 0.25rem;
            right: 2rem;
            width: 18px;
            height: 18px;
            border-radius: 3px;
            background: #dc3545;
            color: white;
            border: none;
            font-size: 0.7rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .ticket-card:hover .btn-unschedule {
            opacity: 1;
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
        }
        
        .ticket-card:hover .resize-handle {
            opacity: 1;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            transition: right 0.3s;
            z-index: 1050;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.active {
            right: 0;
        }
        
        .sidebar-header {
            background-color: var(--color-primary);
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
        
        .sidebar-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 0.6rem;
            margin-bottom: 0.5rem;
            cursor: move;
            border-left: 3px solid var(--color-secondary);
        }
        
        .sidebar-card:hover {
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        
        .drag-over {
            background-color: #e3f2fd !important;
        }
        
        .resizing {
            cursor: ew-resize !important;
            user-select: none;
        }
        
        .resizing * {
            cursor: ew-resize !important;
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <div class="container-fluid">
            <h4 class="mb-0">Programación Semanal de Solicitudes</h4>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="week-nav">
                <button class="btn btn-sm btn-outline-secondary" onclick="cambiarSemana(-1)">
                    <i class="fas fa-chevron-left"></i> Anterior
                </button>
                <strong>Semana <span id="semana-numero"><?= $semana_actual ?></span></strong>
                <button class="btn btn-sm btn-outline-secondary" onclick="cambiarSemana(1)">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <button class="btn btn-primary" onclick="toggleSidebar()">
                <i class="fas fa-list"></i> Solicitudes Pendientes
            </button>
        </div>
        
        <div class="schedule-container">
            <div id="schedule-grid" class="schedule-grid">
                <!-- Se llenará con JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0">Solicitudes Sin Programar</h5>
            <button class="btn btn-sm btn-light" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="sidebar-body">
            <div class="mb-3">
                <label class="form-label">Filtrar por Sucursal:</label>
                <select id="filtro-sucursal" class="form-select form-select-sm" onchange="cargarTicketsSinProgramar()">
                    <option value="">Todas las sucursales</option>
                    <?php foreach ($sucursales as $suc): ?>
                        <option value="<?= htmlspecialchars($suc['cod_sucursal']) ?>">
                            <?= htmlspecialchars($suc['nombre_sucursal']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="tickets-sin-programar">
                <!-- Se llenará con JavaScript -->
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let semanaActual = <?= $semana_actual ?>;
        let fechasSemana = [];
        let equiposTrabajo = [];
        let resizing = null;
        let draggedTicket = null;
        
        // ========== FUNCIONES PRINCIPALES ==========
        
        function mostrarDetallesTicket(ticketId) {
            console.log('Mostrar detalles:', ticketId);
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
                            console.log('🔄 Reseteando urgencia de', window.currentUrgency, 'a', newUrgency);
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
        
        function cambiarSemana(delta) {
            semanaActual += delta;
            document.getElementById('semana-numero').textContent = semanaActual;
            cargarCronograma();
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                cargarTicketsSinProgramar();
            }
        }
        
        function cargarCronograma() {
            $.ajax({
                url: 'ajax/agenda_get_cronograma.php',
                method: 'GET',
                data: { semana: semanaActual },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        console.error('Error del servidor:', data);
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    fechasSemana = data.fechas;
                    equiposTrabajo = data.equipos;
                    renderizarCronograma(data);
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    alert('Error al cargar el cronograma. Por favor revisa la consola.');
                }
            });
        }
        
        // ========== RENDERIZADO DEL CRONOGRAMA ==========
        
        function renderizarCronograma(data) {
            const grid = document.getElementById('schedule-grid');
            grid.innerHTML = '';
            
            // Headers
            const headerEquipo = document.createElement('div');
            headerEquipo.className = 'schedule-header team-label';
            headerEquipo.textContent = 'Equipo de Trabajo';
            grid.appendChild(headerEquipo);
            
            data.fechas.forEach(fecha => {
                const header = document.createElement('div');
                header.className = 'schedule-header';
                const d = new Date(fecha.fecha + 'T00:00:00');
                const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                header.innerHTML = `${dias[d.getDay()]}<br>${fecha.fecha_formato}`;
                grid.appendChild(header);
            });
            
            // Crear filas por equipo
            data.equipos.forEach(equipo => {
                // Celda de nombre de equipo
                const teamCell = document.createElement('div');
                teamCell.className = 'team-cell';
                teamCell.textContent = equipo.nombre;
                teamCell.dataset.equipo = equipo.id;
                grid.appendChild(teamCell);
                
                // Calcular posiciones de tickets
                const filasOcupacion = calcularPosiciones(equipo.tickets, data.fechas);
                
                // Crear celdas de días
                data.fechas.forEach((fecha, diaIdx) => {
                    const dayCell = document.createElement('div');
                    dayCell.className = 'day-cell';
                    dayCell.dataset.fecha = fecha.fecha;
                    dayCell.dataset.equipo = equipo.id;
                    dayCell.dataset.tipoFormulario = equipo.tipo_formulario;
                    
                    const alturaMinima = (filasOcupacion.length * 60) + 20;
                    dayCell.style.minHeight = Math.max(80, alturaMinima) + 'px';
                    
                    dayCell.addEventListener('dragover', handleDragOver);
                    dayCell.addEventListener('drop', handleDrop);
                    dayCell.addEventListener('dragleave', handleDragLeave);
                    
                    grid.appendChild(dayCell);
                });
                
                // Renderizar tickets
                equipo.tickets.forEach(ticket => {
                    renderizarTicket(ticket, data.fechas, equipo.id, filasOcupacion);
                });
            });
        }
        
        function calcularPosiciones(tickets, fechas) {
            const filas = [];
            const posiciones = new Map();
            
            tickets.forEach(ticket => {
                const fechaInicio = ticket.fecha_inicio;
                const fechaFinal = ticket.fecha_final;
                
                const diaInicio = fechas.findIndex(f => f.fecha === fechaInicio);
                if (diaInicio === -1) return;
                
                const diaFinal = fechas.findIndex(f => f.fecha === fechaFinal);
                const numDias = Math.min(
                    (diaFinal >= 0 ? diaFinal : fechas.length - 1) - diaInicio + 1,
                    fechas.length - diaInicio
                );
                
                let filaAsignada = -1;
                for (let i = 0; i < filas.length; i++) {
                    let cabe = true;
                    for (let j = 0; j < numDias; j++) {
                        if (filas[i][diaInicio + j]) {
                            cabe = false;
                            break;
                        }
                    }
                    if (cabe) {
                        filaAsignada = i;
                        break;
                    }
                }
                
                if (filaAsignada === -1) {
                    filaAsignada = filas.length;
                    filas.push(Array(fechas.length).fill(false));
                }
                
                for (let j = 0; j < numDias; j++) {
                    filas[filaAsignada][diaInicio + j] = true;
                }
                
                posiciones.set(ticket.id, {
                    fila: filaAsignada,
                    diaInicio: diaInicio,
                    numDias: numDias
                });
            });
            
            tickets.forEach(ticket => {
                ticket._posicion = posiciones.get(ticket.id);
            });
            
            return filas;
        }
        
        function renderizarTicket(ticket, fechas, equipoId, filasOcupacion) {
            if (!ticket._posicion) return;
            
            const { fila, diaInicio, numDias } = ticket._posicion;
            
            const selector = `.day-cell[data-fecha="${fechas[diaInicio].fecha}"][data-equipo="${equipoId}"]`;
            const celda = document.querySelector(selector);
            if (!celda) return;
            
            const card = document.createElement('div');
            card.className = 'ticket-card';
            card.draggable = true;
            card.dataset.ticketId = ticket.id;
            card.dataset.tipoFormulario = ticket.tipo_formulario;
            
            const celdaWidth = celda.offsetWidth || 150;
            const anchoCard = (celdaWidth * numDias) + (1 * (numDias - 1)) - 10;
            
            card.style.left = '5px';
            card.style.top = (fila * 60 + 5) + 'px';
            card.style.width = anchoCard + 'px';
            
            card.innerHTML = `
                <div class="ticket-title">${escapeHtml(ticket.titulo)}</div>
                <div class="ticket-sucursal">${escapeHtml(ticket.nombre_sucursal)}</div>
                <div class="urgency-badge" style="background-color: ${getUrgencyColor(ticket.nivel_urgencia)}">
                    ${ticket.nivel_urgencia || '?'}
                </div>
                <button class="btn-unschedule" onclick="desprogramarTicket(${ticket.id}, event)">
                    <i class="fas fa-times"></i>
                </button>
                <div class="resize-handle"></div>
            `;
            
            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragend', handleDragEnd);
            card.addEventListener('click', (e) => {
                if (!e.target.closest('.btn-unschedule') && !e.target.closest('.resize-handle')) {
                    mostrarDetallesTicket(ticket.id);
                }
            });
            
            const resizeHandle = card.querySelector('.resize-handle');
            resizeHandle.addEventListener('mousedown', (e) => startResize(e, card, ticket, fechas, diaInicio));
            
            celda.appendChild(card);
        }
        
        // ========== RESIZE ==========
        
        function startResize(e, card, ticket, fechas, diaInicio) {
            e.stopPropagation();
            e.preventDefault();
            
            resizing = {
                card: card,
                ticket: ticket,
                fechas: fechas,
                diaInicio: diaInicio,
                startX: e.pageX,
                startWidth: card.offsetWidth,
                celdaWidth: card.parentElement.offsetWidth
            };
            
            card.draggable = false;
            document.body.classList.add('resizing');
            
            document.addEventListener('mousemove', doResize);
            document.addEventListener('mouseup', stopResize);
        }
        
        function doResize(e) {
            if (!resizing) return;
            
            const deltaX = e.pageX - resizing.startX;
            const nuevoAncho = resizing.startWidth + deltaX;
            
            const numDias = Math.max(1, Math.round((nuevoAncho + 10) / (resizing.celdaWidth + 1)));
            const diasDisponibles = resizing.fechas.length - resizing.diaInicio;
            const diasFinales = Math.min(numDias, diasDisponibles);
            
            const anchoFinal = (resizing.celdaWidth * diasFinales) + (1 * (diasFinales - 1)) - 10;
            resizing.card.style.width = anchoFinal + 'px';
            
            resizing.nuevoNumDias = diasFinales;
        }
        
        function stopResize(e) {
            if (!resizing) return;
            
            document.removeEventListener('mousemove', doResize);
            document.removeEventListener('mouseup', stopResize);
            
            const card = resizing.card;
            const ticket = resizing.ticket;
            const nuevoNumDias = resizing.nuevoNumDias || 1;
            
            card.draggable = true;
            document.body.classList.remove('resizing');
            
            const fechaInicio = ticket.fecha_inicio;
            const diaInicioIdx = resizing.fechas.findIndex(f => f.fecha === fechaInicio);
            const diaFinalIdx = Math.min(diaInicioIdx + nuevoNumDias - 1, resizing.fechas.length - 1);
            const fechaFinal = resizing.fechas[diaFinalIdx].fecha;
            
            resizing = null;
            
            if (fechaFinal !== ticket.fecha_final) {
                $.ajax({
                    url: 'ajax/agenda_actualizar_fechas.php',
                    method: 'POST',
                    data: {
                        ticket_id: ticket.id,
                        fecha_inicio: fechaInicio,
                        fecha_final: fechaFinal
                    },
                    success: function() {
                        cargarCronograma();
                    },
                    error: function() {
                        alert('Error al actualizar fechas');
                        cargarCronograma();
                    }
                });
            }
        }
        
        // ========== DRAG & DROP ==========
        
        function handleDragStart(e) {
            if (resizing) {
                e.preventDefault();
                return;
            }
            
            draggedTicket = {
                id: this.dataset.ticketId,
                tipoFormulario: this.dataset.tipoFormulario,
                fromSidebar: false
            };
            
            this.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        }
        
        function handleDragEnd(e) {
            this.style.opacity = '1';
        }
        
        function handleDragOver(e) {
            if (!draggedTicket) return;
            
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            const equipoTipo = this.dataset.tipoFormulario;
            const ticketTipo = draggedTicket.tipoFormulario;
            
            if ((equipoTipo === 'cambio_equipos' && ticketTipo !== 'cambio_equipos') ||
                (equipoTipo === 'mantenimiento_general' && ticketTipo === 'cambio_equipos')) {
                e.dataTransfer.dropEffect = 'none';
                return;
            }
            
            this.classList.add('drag-over');
        }
        
        function handleDragLeave(e) {
            this.classList.remove('drag-over');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            if (!draggedTicket) return;
            
            const equipoTipo = this.dataset.tipoFormulario;
            const ticketTipo = draggedTicket.tipoFormulario;
            
            if ((equipoTipo === 'cambio_equipos' && ticketTipo !== 'cambio_equipos') ||
                (equipoTipo === 'mantenimiento_general' && ticketTipo === 'cambio_equipos')) {
                alert('No se puede programar este tipo de solicitud en este equipo');
                draggedTicket = null;
                return;
            }
            
            const fecha = this.dataset.fecha;
            const equipoId = this.dataset.equipo;
            const ticketId = draggedTicket.id;
            const fromSidebar = draggedTicket.fromSidebar;
            
            const url = fromSidebar ? 'ajax/agenda_asignar_ticket.php' : 'ajax/agenda_mover_ticket.php';
            
            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    ticket_id: ticketId,
                    equipo_id: equipoId,
                    fecha_inicio: fecha,
                    fecha_final: fecha
                },
                success: function() {
                    cargarCronograma();
                    if (fromSidebar) {
                        cargarTicketsSinProgramar();
                    }
                },
                error: function() {
                    alert('Error al programar la solicitud');
                }
            });
            
            draggedTicket = null;
        }
        
        // ========== SIDEBAR ==========
        
        function cargarTicketsSinProgramar() {
            const filtroSucursal = document.getElementById('filtro-sucursal').value;
            
            $.ajax({
                url: 'ajax/agenda_get_tickets_pendientes.php',
                method: 'GET',
                data: { sucursal: filtroSucursal },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        console.error('Error del servidor:', data);
                        alert('Error: ' + data.error);
                        return;
                    }
                    renderizarTicketsPendientes(data);
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    alert('Error al cargar solicitudes pendientes');
                }
            });
        }
        
        function renderizarTicketsPendientes(tickets) {
            const container = document.getElementById('tickets-sin-programar');
            container.innerHTML = '';
            
            if (tickets.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No hay solicitudes pendientes</p>';
                return;
            }
            
            tickets.forEach(ticket => {
                const card = document.createElement('div');
                card.className = 'sidebar-card';
                card.draggable = true;
                card.dataset.ticketId = ticket.id;
                card.dataset.tipoFormulario = ticket.tipo_formulario;
                
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.3rem;">
                        <div style="font-weight: 600; font-size: 0.85rem; flex: 1; padding-right: 0.5rem;">
                            ${escapeHtml(ticket.titulo)}
                        </div>
                        <div class="urgency-badge" style="background-color: ${getUrgencyColor(ticket.nivel_urgencia)}; position: static; margin-left: 0.5rem;">
                            ${ticket.nivel_urgencia || '?'}
                        </div>
                    </div>
                    <div style="font-size: 0.75rem; color: #6c757d;">
                        ${escapeHtml(ticket.nombre_sucursal)}
                    </div>
                    <div style="font-size: 0.7rem; color: #999; margin-top: 0.2rem;">
                        ${ticket.tipo_formulario === 'cambio_equipos' ? 'Cambio de Equipos' : 'Mantenimiento General'}
                    </div>
                `;
                
                card.addEventListener('dragstart', function(e) {
                    draggedTicket = {
                        id: this.dataset.ticketId,
                        tipoFormulario: this.dataset.tipoFormulario,
                        fromSidebar: true
                    };
                    this.style.opacity = '0.5';
                    e.dataTransfer.effectAllowed = 'move';
                });
                
                card.addEventListener('dragend', function(e) {
                    this.style.opacity = '1';
                });
                
                card.addEventListener('click', () => mostrarDetallesTicket(ticket.id));
                
                container.appendChild(card);
            });
        }
        
        // ========== UTILIDADES ==========
        
        function getUrgencyColor(nivel) {
            switch(parseInt(nivel)) {
                case 1: return '#28a745';
                case 2: return '#ffc107';
                case 3: return '#fd7e14';
                case 4: return '#dc3545';
                default: return '#8b8b8b';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function desprogramarTicket(ticketId, event) {
            event.stopPropagation();
            
            if (!confirm('¿Desea desprogramar esta solicitud?')) return;
            
            $.ajax({
                url: 'ajax/agenda_desprogramar_ticket.php',
                method: 'POST',
                data: { ticket_id: ticketId },
                success: function() {
                    cargarCronograma();
                },
                error: function() {
                    alert('Error al desprogramar');
                }
            });
        }
        
        // ========== INICIALIZACIÓN ==========
        
        $(document).ready(function() {
            cargarCronograma();
        });
    </script>
</body>
</html>