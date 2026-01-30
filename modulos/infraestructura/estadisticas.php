<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Tickets de Mantenimiento</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .header .subtitle {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        .current-week {
            background-color: #3498db;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        .card h2 {
            margin-top: 0;
            color: #3498db;
            font-size: 1.2rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .card .card-info {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .loading {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }
        .error {
            background-color: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .chart-container {
            height: 300px;
            position: relative;
        }
        .week-high {
            background-color: #fff2cc;
        }
        .week-critical {
            background-color: #f8cecc;
            font-weight: bold;
        }
        .week-current {
            background-color: #d4edda;
            font-weight: bold;
        }
        .percentage-good {
            color: #27ae60;
            font-weight: bold;
        }
        .percentage-bad {
            color: #e74c3c;
            font-weight: bold;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #3498db;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        .meta-info {
            background-color: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .percentage-meta {
            background-color: #fff2cc;
            border-left: 4px solid #f39c12;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Estadísticas de Tickets de Mantenimiento</h1>
            <p>Resumen de tickets de nivel de urgencia 4 y tiempo de atención general</p>
            <div class="subtitle" id="weeks-range">Cargando rango de semanas...</div>
            <div id="current-week" class="current-week" style="display: none;">Semana actual: <span id="current-week-number"></span></div>
        </div>

        <div id="error-message" class="error" style="display: none;"></div>

        <!-- Resumen estadístico -->
        <div id="stats-summary" class="stats-summary" style="display: none;">
            <div class="stat-card">
                <div class="stat-value" id="total-urgency4">0</div>
                <div class="stat-label">Total Tickets Urgencia 4</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="total-tickets-all">0</div>
                <div class="stat-label">Total Tickets Recibidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="avg-percentage">0%</div>
                <div class="stat-label">% Promedio Urgencia 4</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="avg-attention-time">0</div>
                <div class="stat-label">Tiempo Promedio Atención (días)</div>
            </div>
        </div>

        <div class="stats-container">
            <div class="card">
                <h2>Tickets de Urgencia 4 por Semana</h2>
                <div class="percentage-meta">
                    <strong>Meta:</strong> Menos del 2% de tickets deben ser de urgencia nivel 4
                </div>
                <div class="card-info">Cantidad de tickets con nivel de urgencia 4 y porcentaje sobre el total semanal</div>
                <div id="table-container">
                    <div class="loading">Cargando datos...</div>
                </div>
            </div>
            
            <div class="card">
                <h2>Gráfico de Tiempo de Atención General</h2>
                <div class="meta-info">
                    <strong>Línea meta:</strong> 7 días (objetivo máximo de tiempo de atención)
                </div>
                <div class="card-info">Tiempo promedio (días) entre creación y finalización de TODOS los tickets</div>
                <div class="chart-container">
                    <canvas id="attention-time-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales para almacenar datos
        let ticketsData = [];
        let attentionTimeData = [];
        let currentWeek = 0;

        // Función para mostrar errores
        function showError(message) {
            const errorDiv = document.getElementById('error-message');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }

        // Función para cargar datos desde el servidor
        async function loadData() {
            try {
                const response = await fetch('get_tickets_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    ticketsData = data.ticketsByWeek;
                    attentionTimeData = data.attentionTimeByWeek;
                    currentWeek = data.currentWeek;
                    
                    // Actualizar el rango de semanas
                    if (data.weeksRange) {
                        document.getElementById('weeks-range').textContent = 
                            `Semanas ${data.weeksRange.start} a ${data.weeksRange.end} (${data.weeksRange.total_weeks} semanas)`;
                        
                        document.getElementById('current-week-number').textContent = data.currentWeek;
                        document.getElementById('current-week').style.display = 'inline-block';
                    }
                    
                    updateSummaryStats();
                    renderTable();
                    renderChart();
                } else {
                    showError(data.message || 'Error al cargar los datos');
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error de conexión con el servidor');
            }
        }

        // Función para actualizar las estadísticas de resumen
        function updateSummaryStats() {
            const totalUrgency4 = ticketsData.reduce((sum, item) => sum + item.tickets_urgencia_4, 0);
            const totalTicketsAll = ticketsData.reduce((sum, item) => sum + item.total_tickets, 0);
            const totalTicketsFinalized = attentionTimeData.reduce((sum, item) => sum + item.total_tickets, 0);
            
            // Calcular porcentaje promedio de urgencia 4 (ponderado por total de tickets)
            let totalPercentage = 0;
            let totalWeight = 0;
            
            ticketsData.forEach(item => {
                if (item.total_tickets > 0) {
                    totalPercentage += item.porcentaje_urgencia_4 * item.total_tickets;
                    totalWeight += item.total_tickets;
                }
            });
            
            const avgPercentage = totalWeight > 0 ? (totalPercentage / totalWeight) : 0;
            
            // Calcular tiempo promedio general (ponderado por cantidad de tickets)
            let totalTime = 0;
            let totalTimeWeight = 0;
            
            attentionTimeData.forEach(item => {
                if (item.total_tickets > 0) {
                    totalTime += item.tiempo_promedio * item.total_tickets;
                    totalTimeWeight += item.total_tickets;
                }
            });
            
            const avgAttentionTime = totalTimeWeight > 0 ? (totalTime / totalTimeWeight) : 0;
            
            // Actualizar DOM
            document.getElementById('total-urgency4').textContent = totalUrgency4;
            document.getElementById('total-tickets-all').textContent = totalTicketsAll;
            document.getElementById('avg-percentage').textContent = avgPercentage.toFixed(1) + '%';
            document.getElementById('avg-attention-time').textContent = avgAttentionTime.toFixed(1);
            
            // Resaltar el porcentaje promedio si es mayor a la meta
            const avgPercentageElement = document.getElementById('avg-percentage');
            if (avgPercentage > 2) {
                avgPercentageElement.style.color = '#e74c3c';
            } else {
                avgPercentageElement.style.color = '#27ae60';
            }
            
            // Mostrar el resumen
            document.getElementById('stats-summary').style.display = 'grid';
        }

        // Función para determinar la clase CSS según la cantidad de tickets
        function getWeekClass(cantidad, semana) {
            if (semana === currentWeek) return 'week-current';
            if (cantidad >= 10) return 'week-critical';
            if (cantidad >= 5) return 'week-high';
            return '';
        }

        // Función para determinar la clase CSS del porcentaje
        function getPercentageClass(porcentaje) {
            return porcentaje <= 2 ? 'percentage-good' : 'percentage-bad';
        }

        // Función para renderizar la tabla
        function renderTable() {
            const tableContainer = document.getElementById('table-container');
            
            if (ticketsData.length === 0) {
                tableContainer.innerHTML = '<div class="loading">No hay datos disponibles</div>';
                return;
            }
            
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Semana</th>
                            <th>Total Tickets</th>
                            <th>Urgencia 4</th>
                            <th>% Urgencia 4</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            ticketsData.forEach(item => {
                let nivel = 'Normal';
                if (item.tickets_urgencia_4 >= 10) nivel = 'Crítico';
                else if (item.tickets_urgencia_4 >= 5) nivel = 'Alto';
                if (item.semana === currentWeek) nivel += ' (Actual)';
                
                const porcentaje = item.porcentaje_urgencia_4;
                const estadoPorcentaje = porcentaje <= 2 ? '✅' : '⚠️';
                
                html += `
                    <tr class="${getWeekClass(item.tickets_urgencia_4, item.semana)}">
                        <td>Semana ${item.semana}${item.semana === currentWeek ? ' ⭐' : ''}</td>
                        <td>${item.total_tickets}</td>
                        <td>${item.tickets_urgencia_4}</td>
                        <td class="${getPercentageClass(porcentaje)}">${porcentaje}%</td>
                        <td>${nivel} ${estadoPorcentaje}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            tableContainer.innerHTML = html;
        }

        // Función para renderizar el gráfico con línea meta
        function renderChart() {
            const ctx = document.getElementById('attention-time-chart').getContext('2d');
            
            // Preparar datos para el gráfico
            const semanas = attentionTimeData.map(item => 
                `Semana ${item.semana}${item.semana === currentWeek ? ' (Actual)' : ''}`
            );
            const tiemposPromedio = attentionTimeData.map(item => item.tiempo_promedio);
            const totalTickets = attentionTimeData.map(item => item.total_tickets);
            const ticketsUrgencia4 = attentionTimeData.map(item => item.tickets_urgencia_4);
            
            // Crear array para la línea meta (siempre 7 días)
            const metaLineData = Array(semanas.length).fill(7);
            
            // Colores basados en el tiempo de atención y si es la semana actual
            const backgroundColors = attentionTimeData.map((item, index) => {
                if (item.semana === currentWeek) return 'rgba(52, 152, 219, 0.9)'; // Azul destacado para semana actual
                if (item.tiempo_promedio >= 7) return 'rgba(231, 76, 60, 0.7)';  // Rojo para >7 días
                if (item.tiempo_promedio >= 3) return 'rgba(243, 156, 18, 0.7)'; // Naranja para 3-7 días
                return 'rgba(46, 204, 113, 0.7)';                               // Verde para <3 días
            });
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: semanas,
                    datasets: [
                        {
                            label: 'Tiempo promedio de atención (días)',
                            data: tiemposPromedio,
                            backgroundColor: backgroundColors,
                            borderColor: backgroundColors.map(color => color.replace('0.7', '1').replace('0.9', '1')),
                            borderWidth: 1,
                            order: 2 // Orden para que las barras estén detrás de la línea
                        },
                        {
                            label: 'Meta (7 días)',
                            data: metaLineData,
                            type: 'line',
                            borderColor: 'rgba(155, 89, 182, 0.8)',
                            borderWidth: 3,
                            borderDash: [5, 5],
                            fill: false,
                            pointStyle: false,
                            pointRadius: 0,
                            tension: 0,
                            order: 1 // Orden para que la línea esté encima de las barras
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Días'
                            },
                            suggestedMax: Math.max(10, ...tiemposPromedio) + 2 // Espacio para la línea meta
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Semanas'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'line'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        // Tooltip para las barras
                                        const index = context.dataIndex;
                                        const time = context.raw;
                                        const tickets = totalTickets[index];
                                        const urgency4 = ticketsUrgencia4[index];
                                        return [
                                            `Tiempo promedio: ${time.toFixed(2)} días`,
                                            `Total tickets finalizados: ${tickets}`,
                                            `Tickets urgencia 4: ${urgency4}`
                                        ];
                                    } else {
                                        // Tooltip para la línea meta
                                        return `Meta objetivo: ${context.raw} días`;
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }

        // Cargar datos cuando la página esté lista
        document.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>
</html>