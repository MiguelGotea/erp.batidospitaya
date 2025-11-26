<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';

$ticket = new Ticket();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificador Semanal de Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .header {
            background: #0E544C;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .week-navigation {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .week-navigation button {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .week-navigation button:hover {
            background: #459d93;
        }

        .week-info {
            font-size: 16px;
            font-weight: 500;
        }

        .controls {
            background: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e0e0;
        }

        .btn-sidebar {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .btn-sidebar:hover {
            background: #459d93;
        }

        .main-container {
            display: flex;
            gap: 0;
            background: white;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .scheduler-container {
            flex: 1;
            overflow-x: auto;
            padding: 20px;
        }

        .scheduler-grid {
            display: grid;
            grid-template-columns: 200px repeat(6, 1fr);
            gap: 1px;
            background: #e0e0e0;
            border: 1px solid #e0e0e0;
            min-width: 1200px;
        }

        .grid-header {
            background: #0E544C;
            color: white;
            padding: 15px 10px;
            font-weight: 600;
            font-size: 13px;
            text-align: center;
        }

        .grid-header.team-label {
            text-align: left;
            padding-left: 15px;
        }

        .day-header {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .day-name {
            font-size: 12px;
            opacity: 0.9;
        }

        .day-date {
            font-size: 14px;
            font-weight: 700;
        }

        .team-row-label {
            background: #f8f9fa;
            padding: 15px;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            border-right: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
        }

        .day-cell {
            background: white;
            padding: 8px;
            min-height: 120px;
            position: relative;
        }

        .ticket-card {
            background: white;
            border: 2px solid #51B8AC;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 8px;
            cursor: move;
            position: relative;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .ticket-card.multi-day {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 10;
            margin-bottom: 0;
        }

        .ticket-card:hover {
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }

        .ticket-card.dragging {
            opacity: 0.5;
        }

        .ticket-card .resize-handle {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 10px;
            cursor: ew-resize;
            background: rgba(81, 184, 172, 0.3);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .ticket-card:hover .resize-handle {
            opacity: 1;
        }

        .urgency-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: white;
        }

        .urgency-1 { background: #28a745; }
        .urgency-2 { background: #ffc107; color: #333; }
        .urgency-3 { background: #fd7e14; }
        .urgency-4 { background: #dc3545; }
        .urgency-none { background: #8b8b8b; }

        .ticket-title {
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
            padding-right: 25px;
            line-height: 1.3;
        }

        .ticket-sucursal {
            font-size: 11px;
            color: #666;
        }

        .sidebar {
            width: 0;
            background: white;
            border-left: 1px solid #e0e0e0;
            overflow: hidden;
            transition: width 0.3s ease;
        }

        .sidebar.open {
            width: 350px;
        }

        .sidebar-header {
            background: #0E544C;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .btn-close-sidebar {
            background: transparent;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-filters {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .sidebar-filters select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 13px;
        }

        .sidebar-content {
            padding: 15px 20px;
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }

        .unscheduled-ticket {
            background: white;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: move;
            position: relative;
            transition: all 0.2s;
        }

        .unscheduled-ticket:hover {
            border-color: #51B8AC;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .day-cell.drag-over {
            background: #e8f5f3;
            border: 2px dashed #51B8AC;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            font-size: 14px;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 Planificador Semanal de Mantenimiento</h1>
        <div class="week-navigation">
            <button onclick="previousWeek()">← Anterior</button>
            <span class="week-info">Semana <span id="weekNumber">-</span></span>
            <button onclick="nextWeek()">Siguiente →</button>
        </div>
    </div>

    <div class="controls">
        <div></div>
        <button class="btn-sidebar" onclick="toggleSidebar()">📋 Solicitudes sin Programar</button>
    </div>

    <div class="main-container">
        <div class="scheduler-container">
            <div id="schedulerLoading" class="loading">Cargando cronograma...</div>
            <div class="scheduler-grid" id="schedulerGrid" style="display: none;"></div>
        </div>

        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Solicitudes sin Programar</h3>
                <button class="btn-close-sidebar" onclick="toggleSidebar()">×</button>
            </div>
            <div class="sidebar-filters">
                <select id="sucursalFilter" onchange="filterUnscheduled()">
                    <option value="">Todas las sucursales</option>
                </select>
            </div>
            <div class="sidebar-content" id="sidebarContent">
                <div class="loading">Cargando solicitudes...</div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentWeek = 518;
        let sidebarOpen = false;
        let workTeams = [];
        let weekDates = [];
        let allUnscheduledTickets = [];
        let isResizing = false;
        let resizeData = null;

        $(document).ready(function() {
            loadCurrentWeek();
        });

        function loadCurrentWeek() {
            $.ajax({
                url: 'ajax/planificador/get_current_week.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    currentWeek = parseInt(response.week_number);
                    loadWeekData();
                },
                error: function(xhr, status, error) {
                    console.error('Error al cargar semana actual:', xhr.responseText);
                    alert('Error al cargar la semana actual');
                }
            });
        }

        function loadWeekData() {
            $('#schedulerLoading').show();
            $('#schedulerGrid').hide();

            $.ajax({
                url: 'ajax/planificador/get_week_data.php',
                method: 'GET',
                data: { week_number: currentWeek },
                dataType: 'json',
                success: function(response) {
                    weekDates = response.dates;
                    workTeams = response.work_teams;
                    renderScheduler(response.scheduled_tickets);
                    $('#schedulerLoading').hide();
                    $('#schedulerGrid').show();
                },
                error: function(xhr, status, error) {
                    console.error('Error:', xhr.responseText);
                    alert('Error al cargar los datos de la semana');
                    $('#schedulerLoading').hide();
                }
            });
        }

        function loadUnscheduledTickets() {
            $.ajax({
                url: 'ajax/planificador/get_unscheduled_tickets.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    allUnscheduledTickets = response.tickets;
                    loadSucursales(response.sucursales);
                    renderUnscheduled();
                },
                error: function(xhr) {
                    $('#sidebarContent').html('<div class="empty-state">Error al cargar solicitudes</div>');
                }
            });
        }

        function loadSucursales(sucursales) {
            const select = $('#sucursalFilter');
            select.html('<option value="">Todas las sucursales</option>');
            sucursales.forEach(suc => {
                select.append(`<option value="${suc.cod_sucursal}">${suc.nombre_sucursal}</option>`);
            });
        }

        function renderScheduler(scheduledTickets) {
            const grid = $('#schedulerGrid');
            const gridWidth = 1200; // Ancho mínimo del grid
            const dayWidth = (gridWidth - 200) / 6; // Ancho de cada celda de día
            
            let html = '<div class="grid-header team-label">Equipo de Trabajo</div>';
            weekDates.forEach(d => {
                html += `<div class="grid-header">
                    <div class="day-header">
                        <div class="day-name">${d.day_name}</div>
                        <div class="day-date">${d.date_formatted}</div>
                    </div>
                </div>`;
            });

            workTeams.forEach(team => {
                html += `<div class="team-row-label">${team.team_name}</div>`;
                
                for (let dayIndex = 0; dayIndex < 6; dayIndex++) {
                    const dayDate = weekDates[dayIndex].fecha;
                    
                    html += `<div class="day-cell" 
                        data-team="${team.team_key}" 
                        data-day="${dayIndex}" 
                        data-date="${dayDate}"
                        data-is-cambio="${team.is_cambio_equipos ? 1 : 0}"
                        ondrop="drop(event)" 
                        ondragover="allowDrop(event)" 
                        ondragleave="dragLeave(event)">`;
                    
                    // Renderizar tickets que inician en este día
                    scheduledTickets.forEach(ticket => {
                        if (ticket.team_key === team.team_key && ticket.fecha_inicio === dayDate) {
                            // Calcular cuántos días ocupa el ticket
                            const startIndex = dayIndex;
                            let endIndex = dayIndex;
                            
                            for (let i = dayIndex; i < 6; i++) {
                                if (weekDates[i].fecha <= ticket.fecha_final) {
                                    endIndex = i;
                                } else {
                                    break;
                                }
                            }
                            
                            const span = endIndex - startIndex + 1;
                            const width = (span * dayWidth) - 16; // Restar padding
                            
                            html += createTicketCard(ticket, span, width);
                        }
                    });
                    
                    html += '</div>';
                }
            });

            grid.html(html);
            $('#weekNumber').text(currentWeek);
            
            // Inicializar resize handlers
            initializeResizeHandlers();
        }

        function createTicketCard(ticket, span, width) {
            const urgencyClass = ticket.nivel_urgencia ? `urgency-${ticket.nivel_urgencia}` : 'urgency-none';
            const urgencyNum = ticket.nivel_urgencia || '?';
            const isMultiDay = span > 1;
            
            let style = '';
            if (isMultiDay) {
                style = `style="width: ${width}px;"`;
            }
            
            return `
                <div class="ticket-card ${isMultiDay ? 'multi-day' : ''}" 
                    ${style}
                    draggable="true" 
                    ondragstart="drag(event)" 
                    data-ticket-id="${ticket.id}"
                    data-tipo="${ticket.tipo_formulario}"
                    data-fecha-inicio="${ticket.fecha_inicio}"
                    data-fecha-final="${ticket.fecha_final}"
                    data-team="${ticket.team_key}">
                    <div class="urgency-badge ${urgencyClass}">${urgencyNum}</div>
                    <div class="ticket-title" onclick="showTicketDetails(${ticket.id}); event.stopPropagation();">${ticket.titulo}</div>
                    <div class="ticket-sucursal">${ticket.nombre_sucursal}</div>
                    <div class="resize-handle" data-ticket-id="${ticket.id}"></div>
                </div>
            `;
        }

        function initializeResizeHandlers() {
            $('.resize-handle').off('mousedown').on('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const ticketCard = $(this).closest('.ticket-card');
                const ticketId = $(this).data('ticket-id');
                const fechaInicio = ticketCard.data('fecha-inicio');
                const fechaFinal = ticketCard.data('fecha-final');
                const team = ticketCard.data('team');
                
                isResizing = true;
                resizeData = {
                    ticketId: ticketId,
                    ticketCard: ticketCard,
                    startX: e.pageX,
                    originalWidth: ticketCard.width(),
                    fechaInicio: fechaInicio,
                    fechaFinal: fechaFinal,
                    team: team
                };
                
                $('body').css('cursor', 'ew-resize');
            });
        }

        $(document).on('mousemove', function(e) {
            if (!isResizing || !resizeData) return;
            
            const deltaX = e.pageX - resizeData.startX;
            const newWidth = resizeData.originalWidth + deltaX;
            
            if (newWidth > 100) {
                resizeData.ticketCard.css('width', newWidth + 'px');
            }
        });

        $(document).on('mouseup', function(e) {
            if (!isResizing) return;
            
            const gridWidth = 1200;
            const dayWidth = (gridWidth - 200) / 6;
            const finalWidth = resizeData.ticketCard.width();
            
            // Calcular cuántos días ocupa ahora
            const daysSpan = Math.max(1, Math.round((finalWidth + 16) / dayWidth));
            
            // Encontrar el índice del día de inicio
            let startDayIndex = -1;
            for (let i = 0; i < weekDates.length; i++) {
                if (weekDates[i].fecha === resizeData.fechaInicio) {
                    startDayIndex = i;
                    break;
                }
            }
            
            if (startDayIndex >= 0) {
                const endDayIndex = Math.min(startDayIndex + daysSpan - 1, 5);
                const newFechaFinal = weekDates[endDayIndex].fecha;
                
                // Actualizar en el servidor
                $.ajax({
                    url: 'ajax/planificador/update_ticket_dates.php',
                    method: 'POST',
                    data: {
                        ticket_id: resizeData.ticketId,
                        fecha_inicio: resizeData.fechaInicio,
                        fecha_final: newFechaFinal
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadWeekData();
                        } else {
                            alert('Error: ' + response.message);
                            loadWeekData();
                        }
                    },
                    error: function() {
                        alert('Error al actualizar las fechas');
                        loadWeekData();
                    }
                });
            }
            
            isResizing = false;
            resizeData = null;
            $('body').css('cursor', 'default');
        });

        function renderUnscheduled() {
            const content = $('#sidebarContent');
            const filter = $('#sucursalFilter').val();
            
            const filtered = allUnscheduledTickets.filter(t => 
                !filter || t.cod_sucursal === filter
            );

            if (filtered.length === 0) {
                content.html('<div class="empty-state">No hay solicitudes sin programar</div>');
                return;
            }

            let html = '';
            filtered.forEach(ticket => {
                const urgencyClass = ticket.nivel_urgencia ? `urgency-${ticket.nivel_urgencia}` : 'urgency-none';
                const urgencyNum = ticket.nivel_urgencia || '?';
                
                html += `
                    <div class="unscheduled-ticket" 
                        draggable="true" 
                        ondragstart="drag(event)"
                        onclick="showTicketDetails(${ticket.id})"
                        data-ticket-id="${ticket.id}"
                        data-tipo="${ticket.tipo_formulario}">
                        <div class="urgency-badge ${urgencyClass}">${urgencyNum}</div>
                        <div class="ticket-title">${ticket.titulo}</div>
                        <div class="ticket-sucursal">${ticket.nombre_sucursal}</div>
                    </div>
                `;
            });

            content.html(html);
        }

        function toggleSidebar() {
            sidebarOpen = !sidebarOpen;
            $('#sidebar').toggleClass('open');
            
            if (sidebarOpen && allUnscheduledTickets.length === 0) {
                loadUnscheduledTickets();
            }
        }

        function filterUnscheduled() {
            renderUnscheduled();
        }

        function previousWeek() {
            currentWeek--;
            loadWeekData();
        }

        function nextWeek() {
            currentWeek++;
            loadWeekData();
        }

        function drag(event) {
            if (isResizing) {
                event.preventDefault();
                return;
            }
            
            const ticketId = $(event.target).closest('[data-ticket-id]').data('ticket-id');
            const tipo = $(event.target).closest('[data-tipo]').data('tipo');
            event.dataTransfer.setData('ticketId', ticketId);
            event.dataTransfer.setData('tipo', tipo);
            $(event.target).addClass('dragging');
        }

        function allowDrop(event) {
            event.preventDefault();
            $(event.currentTarget).addClass('drag-over');
        }

        function dragLeave(event) {
            $(event.currentTarget).removeClass('drag-over');
        }

        function drop(event) {
            event.preventDefault();
            $(event.currentTarget).removeClass('drag-over');
            
            const ticketId = event.dataTransfer.getData('ticketId');
            const tipo = event.dataTransfer.getData('tipo');
            const teamKey = $(event.currentTarget).data('team');
            const targetDate = $(event.currentTarget).data('date');
            const isCambioTeam = $(event.currentTarget).data('is-cambio') == 1;

            if (isCambioTeam && tipo !== 'cambio_equipos') {
                alert('❌ Solo se pueden asignar solicitudes de "Cambio de Equipos" a este grupo');
                return;
            }

            if (!isCambioTeam && tipo === 'cambio_equipos') {
                alert('❌ Las solicitudes de "Cambio de Equipos" solo pueden asignarse al grupo correspondiente');
                return;
            }

            $.ajax({
                url: 'ajax/planificador/assign_ticket.php',
                method: 'POST',
                data: {
                    ticket_id: ticketId,
                    team_key: teamKey,
                    fecha_inicio: targetDate,
                    fecha_final: targetDate
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadWeekData();
                        if (sidebarOpen) {
                            loadUnscheduledTickets();
                        }
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    alert('Error al asignar la solicitud');
                }
            });
        }

        function showTicketDetails(ticketId) {
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
    </script>
</body>
</html>