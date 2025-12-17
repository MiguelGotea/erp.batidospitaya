<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once __DIR__ . '/config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Solo líder de infraestructura
if ($cargoOperario != 35) {
    header('Location: equipos_lista.php');
    exit;
}

// Obtener todas las solicitudes
$solicitudes = $db->fetchAll("
    SELECT 
        s.*,
        e.codigo, e.marca, e.modelo,
        suc.nombre as sucursal,
        o.Nombre as solicitante_nombre, o.Apellido as solicitante_apellido,
        of.Nombre as finalizador_nombre, of.Apellido as finalizador_apellido,
        (SELECT COUNT(*) FROM mtto_equipos_solicitudes_fotos WHERE solicitud_id = s.id) as num_fotos,
        (SELECT COUNT(*) FROM mtto_equipos_mantenimientos WHERE solicitud_id = s.id) as tiene_mantenimiento
    FROM mtto_equipos_solicitudes s
    INNER JOIN mtto_equipos e ON s.equipo_id = e.id
    INNER JOIN sucursales suc ON s.sucursal_id = suc.id
    INNER JOIN Operarios o ON s.solicitado_por = o.CodOperario
    LEFT JOIN Operarios of ON s.finalizado_por = of.CodOperario
    ORDER BY 
        CASE s.estado 
            WHEN 'solicitado' THEN 1 
            ELSE 2 
        END,
        s.fecha_solicitud DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Solicitudes - Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_general.css">
</head>
<body>
    <div class="container-main">
        <div class="page-header">
            <h1 class="page-title">📋 Gestión de Solicitudes de Mantenimiento</h1>
            <a href="equipos_lista.php" class="btn btn-secondary">← Volver</a>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table id="tabla-solicitudes">
                    <thead>
                        <tr>
                            <th class="table-filter-header">
                                ID
                                <span class="filter-icon" data-column="0">▼</span>
                                <div class="filter-dropdown">
                                    <div class="filter-controls">
                                        <div class="filter-sort-btns">
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-solicitudes'), 0, 'asc')">↑ ASC</button>
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-solicitudes'), 0, 'desc')">↓ DESC</button>
                                        </div>
                                        <button class="btn btn-sm btn-danger filter-clear-btn" onclick="clearFilter(this.closest('.filter-dropdown'), document.getElementById('tabla-solicitudes'), 0)">Limpiar</button>
                                        <input type="text" class="filter-search" placeholder="Buscar..." oninput="searchFilterOptions(this)">
                                    </div>
                                    <div class="filter-options"></div>
                                </div>
                            </th>
                            <th class="table-filter-header">
                                Estado
                                <span class="filter-icon" data-column="1">▼</span>
                                <div class="filter-dropdown">
                                    <div class="filter-controls">
                                        <div class="filter-sort-btns">
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-solicitudes'), 1, 'asc')">↑ ASC</button>
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-solicitudes'), 1, 'desc')">↓ DESC</button>
                                        </div>
                                        <button class="btn btn-sm btn-danger filter-clear-btn" onclick="clearFilter(this.closest('.filter-dropdown'), document.getElementById('tabla-solicitudes'), 1)">Limpiar</button>
                                        <input type="text" class="filter-search" placeholder="Buscar..." oninput="searchFilterOptions(this)">
                                    </div>
                                    <div class="filter-options"></div>
                                </div>
                            </th>
                            <th>Equipo</th>
                            <th>Sucursal</th>
                            <th>Fecha Solicitud</th>
                            <th>Solicitante</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $sol): ?>
                        <tr>
                            <td><?= $sol['id'] ?></td>
                            <td>
                                <?php if ($sol['estado'] == 'solicitado'): ?>
                                    <span class="badge badge-warning">Pendiente</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Finalizado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($sol['codigo']) ?></strong><br>
                                <small><?= htmlspecialchars($sol['marca'] . ' ' . $sol['modelo']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($sol['sucursal']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($sol['fecha_solicitud'])) ?></td>
                            <td><?= htmlspecialchars($sol['solicitante_nombre'] . ' ' . $sol['solicitante_apellido']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="verDetalle(<?= $sol['id'] ?>)">
                                    👁️ Ver
                                </button>
                                <?php if ($sol['estado'] == 'solicitado'): ?>
                                <button class="btn btn-sm btn-success" onclick="finalizar(<?= $sol['id'] ?>)">
                                    ✓ Finalizar
                                </button>
                                <a href="equipos_calendario.php" class="btn btn-sm btn-warning" title="Programar Mantenimiento">
                                    📅
                                </a>
                                <a href="equipos_movimientos.php?equipo_id=<?= $sol['equipo_id'] ?>" class="btn btn-sm btn-info" title="Gestionar Movimiento">
                                    🚚
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para ver detalle -->
    <div id="modal-detalle" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Detalle de Solicitud</h2>
                <button class="modal-close" onclick="closeModal('modal-detalle')">&times;</button>
            </div>
            <div class="modal-body" id="detalle-content">
                <div class="loading active">
                    <div class="spinner"></div>
                    <p>Cargando...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modal-detalle')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal para finalizar -->
    <div id="modal-finalizar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Finalizar Solicitud</h2>
                <button class="modal-close" onclick="closeModal('modal-finalizar')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-finalizar" onsubmit="guardarFinalizacion(event)">
                    <input type="hidden" id="finalizar-solicitud-id" name="solicitud_id">
                    
                    <div class="form-group">
                        <label class="form-label">Observaciones de Finalización</label>
                        <textarea name="observaciones" class="form-control" rows="4" 
                                  placeholder="Indique si se realizó mantenimiento en sitio, se envió equipo de cambio, etc."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modal-finalizar')">Cancelar</button>
                <button class="btn btn-success" onclick="document.getElementById('form-finalizar').requestSubmit()">
                    ✓ Finalizar Solicitud
                </button>
            </div>
        </div>
    </div>

    <script src="js/equipos_funciones.js"></script>
    <script>
        function verDetalle(solicitudId) {
            openModal('modal-detalle');
            
            fetch('ajax/equipos_datos.php?accion=detalle_solicitud&id=' + solicitudId)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        mostrarDetalle(result.data);
                    } else {
                        document.getElementById('detalle-content').innerHTML = 
                            '<p class="alert alert-danger">Error al cargar detalle</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('detalle-content').innerHTML = 
                        '<p class="alert alert-danger">Error al cargar detalle</p>';
                });
        }

        function mostrarDetalle(data) {
            let fotosHtml = '';
            if (data.fotos && data.fotos.length > 0) {
                fotosHtml = '<div class="preview-container">';
                data.fotos.forEach(foto => {
                    fotosHtml += `<div class="preview-item"><img src="${foto.ruta_archivo}" alt="Evidencia"></div>`;
                });
                fotosHtml += '</div>';
            }

            const html = `
                <div class="info-row">
                    <span class="info-label">Solicitud ID:</span>
                    <span class="info-value">#${data.id}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Equipo:</span>
                    <span class="info-value"><strong>${data.codigo}</strong> - ${data.marca} ${data.modelo}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sucursal:</span>
                    <span class="info-value">${data.sucursal}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha:</span>
                    <span class="info-value">${formatDate(data.fecha_solicitud)}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Solicitante:</span>
                    <span class="info-value">${data.solicitante_nombre} ${data.solicitante_apellido}</span>
                </div>
                <div class="form-group mt-2">
                    <label class="form-label">Descripción del Problema:</label>
                    <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                        ${data.descripcion_problema}
                    </div>
                </div>
                ${fotosHtml ? '<div class="form-group"><label class="form-label">Evidencias Fotográficas:</label>' + fotosHtml + '</div>' : ''}
                ${data.estado === 'finalizado' ? `
                    <div class="alert alert-success mt-2">
                        <strong>Finalizado el:</strong> ${formatDate(data.fecha_finalizacion)}<br>
                        <strong>Por:</strong> ${data.finalizador_nombre} ${data.finalizador_apellido}<br>
                        ${data.observaciones_finalizacion ? '<strong>Observaciones:</strong> ' + data.observaciones_finalizacion : ''}
                    </div>
                ` : ''}
            `;
            
            document.getElementById('detalle-content').innerHTML = html;
        }

        function finalizar(solicitudId) {
            document.getElementById('finalizar-solicitud-id').value = solicitudId;
            openModal('modal-finalizar');
        }

        function guardarFinalizacion(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            showLoading(true);
            
            fetch('ajax/equipos_solicitud_finalizar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                showLoading(false);
                if (result.success) {
                    showAlert('Solicitud finalizada exitosamente', 'success');
                    closeModal('modal-finalizar');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Error al finalizar solicitud', 'danger');
                }
            })
            .catch(error => {
                showLoading(false);
                console.error('Error:', error);
                showAlert('Error al procesar la solicitud', 'danger');
            });
        }
    </script>
</body>
</html>