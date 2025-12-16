// public_html/modulos/mantenimiento/js/equipos_dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    cargarDashboard();
});

async function cargarDashboard() {
    await Promise.all([
        cargarEstadisticas(),
        cargarPlanMantenimiento(),
        cargarMantenimientosCurso(),
        cargarHistorialMantenimientos(),
        cargarHistorialMovimientos()
    ]);
}

// Cargar estadísticas
async function cargarEstadisticas() {
    try {
        const response = await fetch('ajax/equipos_dashboard_estadisticas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ equipo_id: equipoId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const stats = data.estadisticas;
            document.getElementById('estadisticas').innerHTML = `
                <div class="estadistica-card">
                    <div class="estadistica-numero">${stats.total_mantenimientos}</div>
                    <div class="estadistica-titulo">Total Mantenimientos</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero">${stats.mantenimientos_preventivos}</div>
                    <div class="estadistica-titulo">Preventivos</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero">${stats.mantenimientos_correctivos}</div>
                    <div class="estadistica-titulo">Correctivos</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero">${stats.total_movimientos}</div>
                    <div class="estadistica-titulo">Movimientos</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero">$${parseFloat(stats.costo_total).toFixed(2)}</div>
                    <div class="estadistica-titulo">Costo Total Mantenimientos</div>
                </div>
                <div class="estadistica-card">
                    <div class="estadistica-numero">${stats.ubicacion_actual}</div>
                    <div class="estadistica-titulo">Ubicación Actual</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Cargar plan de mantenimiento
async function cargarPlanMantenimiento() {
    try {
        const response = await fetch('ajax/equipos_dashboard_plan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ equipo_id: equipoId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const plan = data.plan;
            let html = `
                <div class="fila-formulario">
                    <div>
                        <strong>Frecuencia:</strong> Cada ${frecuenciaMantenimiento} meses
                    </div>
                    <div>
                        <strong>Último Mantenimiento:</strong> 
                        ${plan.ultimo_mantenimiento || 'Nunca'}
                    </div>
                    <div>
                        <strong>Próximo Mantenimiento:</strong> 
                        <span style="color: ${plan.color_proximo};">
                            ${plan.proximo_mantenimiento}
                        </span>
                    </div>
                    <div>
                        <strong>Días Restantes:</strong> 
                        <span style="color: ${plan.color_dias};">
                            ${plan.dias_restantes}
                        </span>
                    </div>
                </div>
            `;
            
            document.getElementById('planMantenimiento').innerHTML = html;
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Cargar mantenimientos en curso
async function cargarMantenimientosCurso() {
    try {
        const response = await fetch('ajax/equipos_dashboard_curso.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ equipo_id: equipoId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const container = document.getElementById('mantenimientosCurso');
            
            if (data.mantenimientos.length === 0) {
                container.innerHTML = '<p class="texto-centrado">No hay mantenimientos en curso</p>';
                return;
            }
            
            let html = '';
            data.mantenimientos.forEach(mtto => {
                html += `
                    <div class="tarjeta" style="margin-bottom: 15px; border-left: 4px solid #51B8AC;">
                        <div class="fila-formulario">
                            <div>
                                <strong>Tipo:</strong> 
                                <span class="badge badge-${mtto.tipo_mantenimiento === 'Preventivo' ? 'programado' : 'solicitado'}">
                                    ${mtto.tipo_mantenimiento}
                                </span>
                            </div>
                            <div>
                                <strong>Fecha Programada:</strong> ${mtto.fecha_programada}
                            </div>
                            <div>
                                <strong>Estado:</strong> 
                                <span class="badge badge-proceso">${mtto.estado}</span>
                            </div>
                            <div>
                                <strong>Proveedor:</strong> ${mtto.proveedor_servicio || 'No asignado'}
                            </div>
                        </div>
                        ${mtto.observaciones ? `<p style="margin-top: 10px;"><strong>Obs:</strong> ${mtto.observaciones}</p>` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Cargar historial de mantenimientos
async function cargarHistorialMantenimientos() {
    try {
        const response = await fetch('ajax/equipos_dashboard_historial_mtto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ equipo_id: equipoId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const tbody = document.querySelector('#tablaMantenimientos tbody');
            
            if (data.mantenimientos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="texto-centrado">No hay mantenimientos registrados</td></tr>';
                return;
            }
            
            let html = '';
            data.mantenimientos.forEach(mtto => {
                html += `
                    <tr>
                        <td>${mtto.fecha_realizada || mtto.fecha_programada}</td>
                        <td>
                            <span class="badge badge-${mtto.tipo_mantenimiento === 'Preventivo' ? 'programado' : 'solicitado'}">
                                ${mtto.tipo_mantenimiento}
                            </span>
                        </td>
                        <td>${mtto.proveedor_servicio || 'N/A'}</td>
                        <td>${mtto.trabajo_realizado || 'Pendiente'}</td>
                        <td>$${parseFloat(mtto.costo_total || 0).toFixed(2)}</td>
                        <td>
                            <span class="badge badge-${getEstadoBadgeClass(mtto.estado)}">
                                ${mtto.estado}
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Cargar historial de movimientos
async function cargarHistorialMovimientos() {
    try {
        const response = await fetch('ajax/equipos_dashboard_historial_mov.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ equipo_id: equipoId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const timeline = document.getElementById('timelineMovimientos');
            
            if (data.movimientos.length === 0) {
                timeline.innerHTML = '<p class="texto-centrado">No hay movimientos registrados</p>';
                return;
            }
            
            let html = '';
            data.movimientos.forEach(mov => {
                html += `
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <strong>${mov.tipo_movimiento}</strong><br>
                            <small>${mov.fecha_ejecutada || mov.fecha_planificada}</small><br>
                            ${mov.origen} → ${mov.destino}<br>
                            <span class="badge badge-${getEstadoBadgeClass(mov.estado)}">
                                ${mov.estado}
                            </span>
                            ${mov.observaciones ? `<br><small>${mov.observaciones}</small>` : ''}
                        </div>
                    </div>
                `;
            });
            
            timeline.innerHTML = html;
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Helper para clases de badges
function getEstadoBadgeClass(estado) {
    const badges = {
        'Solicitado': 'solicitado',
        'Agendado': 'agendado',
        'Finalizado': 'finalizado',
        'Programado': 'programado',
        'En Proceso': 'proceso',
        'Completado': 'completado',
        'Planificado': 'programado',
        'En Tránsito': 'proceso'
    };
    return badges[estado] || 'programado';
}