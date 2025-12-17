<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once __DIR__ . '/models/Equipo.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
$codigo_sucursal_busqueda = $sucursales[0]['nombre'];

$equipoModel = new Equipo();

// Obtener equipos según el cargo
if ($cargoOperario == 35) {
    // Líder de infraestructura ve todos los equipos
    $equipos = $equipoModel->obtenerTodos();
} else {
    // Líderes de sucursal solo ven sus equipos
    $codigoSucursal = $sucursales[0]['codigo'];
    $equipos = $equipoModel->obtenerPorSucursal($codigoSucursal);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Equipos - Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_general.css">
</head>
<body>
    <div class="container-main">
        <div class="page-header">
            <h1 class="page-title">📋 Lista de Equipos</h1>
            <div>
                <?php if ($cargoOperario == 35): ?>
                <a href="equipos_registro.php" class="btn btn-primary">➕ Registrar Nuevo Equipo</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table id="tabla-equipos">
                    <thead>
                        <tr>
                            <th class="table-filter-header">
                                Código
                                <span class="filter-icon" data-column="0">▼</span>
                                <div class="filter-dropdown">
                                    <div class="filter-controls">
                                        <div class="filter-sort-btns">
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 0, 'asc')">↑ ASC</button>
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 0, 'desc')">↓ DESC</button>
                                        </div>
                                        <button class="btn btn-sm btn-danger filter-clear-btn" onclick="clearFilter(this.closest('.filter-dropdown'), document.getElementById('tabla-equipos'), 0)">Limpiar</button>
                                        <input type="text" class="filter-search" placeholder="Buscar..." oninput="searchFilterOptions(this)">
                                    </div>
                                    <div class="filter-options"></div>
                                </div>
                            </th>
                            <th class="table-filter-header">
                                Tipo
                                <span class="filter-icon" data-column="1">▼</span>
                                <div class="filter-dropdown">
                                    <div class="filter-controls">
                                        <div class="filter-sort-btns">
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 1, 'asc')">↑ ASC</button>
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 1, 'desc')">↓ DESC</button>
                                        </div>
                                        <button class="btn btn-sm btn-danger filter-clear-btn" onclick="clearFilter(this.closest('.filter-dropdown'), document.getElementById('tabla-equipos'), 1)">Limpiar</button>
                                        <input type="text" class="filter-search" placeholder="Buscar..." oninput="searchFilterOptions(this)">
                                    </div>
                                    <div class="filter-options"></div>
                                </div>
                            </th>
                            <th class="table-filter-header">
                                Marca/Modelo
                                <span class="filter-icon" data-column="2">▼</span>
                                <div class="filter-dropdown">
                                    <div class="filter-controls">
                                        <div class="filter-sort-btns">
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 2, 'asc')">↑ ASC</button>
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 2, 'desc')">↓ DESC</button>
                                        </div>
                                        <button class="btn btn-sm btn-danger filter-clear-btn" onclick="clearFilter(this.closest('.filter-dropdown'), document.getElementById('tabla-equipos'), 2)">Limpiar</button>
                                        <input type="text" class="filter-search" placeholder="Buscar..." oninput="searchFilterOptions(this)">
                                    </div>
                                    <div class="filter-options"></div>
                                </div>
                            </th>
                            <th class="table-filter-header">
                                Ubicación Actual
                                <span class="filter-icon" data-column="3">▼</span>
                                <div class="filter-dropdown">
                                    <div class="filter-controls">
                                        <div class="filter-sort-btns">
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 3, 'asc')">↑ ASC</button>
                                            <button class="btn btn-sm btn-secondary" onclick="sortTable(document.getElementById('tabla-equipos'), 3, 'desc')">↓ DESC</button>
                                        </div>
                                        <button class="btn btn-sm btn-danger filter-clear-btn" onclick="clearFilter(this.closest('.filter-dropdown'), document.getElementById('tabla-equipos'), 3)">Limpiar</button>
                                        <input type="text" class="filter-search" placeholder="Buscar..." oninput="searchFilterOptions(this)">
                                    </div>
                                    <div class="filter-options"></div>
                                </div>
                            </th>
                            <th>Próximo Mtto Preventivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipos as $equipo): ?>
                        <tr>
                            <td><?= htmlspecialchars($equipo['codigo']) ?></td>
                            <td><?= htmlspecialchars($equipo['tipo_nombre']) ?></td>
                            <td><?= htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']) ?></td>
                            <td><?= htmlspecialchars($equipo['ubicacion_actual'] ?? 'Sin ubicación') ?></td>
                            <td>
                                <?php 
                                if ($equipo['proxima_fecha_preventivo']) {
                                    $fecha = new DateTime($equipo['proxima_fecha_preventivo']);
                                    $hoy = new DateTime();
                                    $diferencia = $hoy->diff($fecha);
                                    
                                    if ($fecha < $hoy) {
                                        echo '<span class="badge badge-danger">' . $fecha->format('d/m/Y') . ' (Vencido)</span>';
                                    } else {
                                        echo '<span class="badge badge-success">' . $fecha->format('d/m/Y') . '</span>';
                                    }
                                } else {
                                    echo '<span class="badge badge-secondary">Sin registro</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($equipo['tiene_solicitud_pendiente'] > 0): ?>
                                    <span class="badge badge-warning">Solicitud Pendiente</span>
                                    <?php if ($equipo['fecha_movimiento_programado']): ?>
                                        <br><small>Movimiento: <?= date('d/m/Y', strtotime($equipo['fecha_movimiento_programado'])) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-success">Operativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="equipos_dashboard.php?id=<?= $equipo['id'] ?>" class="btn btn-sm btn-primary" title="Ver Dashboard">
                                    📊 Dashboard
                                </a>
                                <?php if ($cargoOperario == 5 || $cargoOperario == 43): ?>
                                <button class="btn btn-sm btn-warning" onclick="solicitarMantenimiento(<?= $equipo['id'] ?>)" title="Solicitar Mantenimiento">
                                    🔧 Solicitar
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para solicitud de mantenimiento -->
    <div id="modal-solicitud" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Solicitar Mantenimiento Correctivo</h2>
                <button class="modal-close" onclick="closeModal('modal-solicitud')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-solicitud" onsubmit="enviarSolicitud(event)">
                    <input type="hidden" id="solicitud-equipo-id" name="equipo_id">
                    
                    <div class="form-group">
                        <label class="form-label required">Descripción del Problema</label>
                        <textarea class="form-control" name="descripcion_problema" rows="4" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Evidencias Fotográficas (mínimo 1)</label>
                        <div class="upload-container" id="upload-solicitud">
                            <div class="upload-buttons">
                                <button type="button" class="btn btn-primary btn-upload-file">
                                    📁 Subir Archivo
                                </button>
                                <button type="button" class="btn btn-primary btn-upload-camera">
                                    📷 Tomar Foto
                                </button>
                            </div>
                            <input type="file" class="file-input" accept="image/*" multiple>
                            <div class="preview-container"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modal-solicitud')">Cancelar</button>
                <button class="btn btn-primary" onclick="document.getElementById('form-solicitud').requestSubmit()">
                    Enviar Solicitud
                </button>
            </div>
        </div>
    </div>

    <div class="loading">
        <div class="spinner"></div>
        <p>Procesando...</p>
    </div>

    <script src="js/equipos_funciones.js"></script>
    <script>
        function solicitarMantenimiento(equipoId) {
            document.getElementById('solicitud-equipo-id').value = equipoId;
            openModal('modal-solicitud');
            initFileUpload('upload-solicitud');
        }

        function enviarSolicitud(e) {
            e.preventDefault();
            
            if (capturedImages.length === 0) {
                alert('Debe adjuntar al menos una evidencia fotográfica');
                return;
            }

            const formData = new FormData(e.target);
            
            // Agregar imágenes
            capturedImages.forEach((img, index) => {
                formData.append(`imagenes[${index}]`, img.file);
            });

            showLoading(true);

            fetch('ajax/equipos_solicitud_crear.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                showLoading(false);
                if (result.success) {
                    showAlert('Solicitud de mantenimiento creada exitosamente', 'success');
                    closeModal('modal-solicitud');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Error al crear la solicitud', 'danger');
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