<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once __DIR__ . '/config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);

// Obtener movimientos según el rol
if ($cargoOperario == 35) {
    // Líder de infraestructura ve todos
    $movimientos = $db->fetchAll("
        SELECT 
            m.*,
            e.codigo, e.marca, e.modelo,
            so.nombre as sucursal_origen, so.codigo as codigo_origen,
            sd.nombre as sucursal_destino, sd.codigo as codigo_destino,
            op.Nombre as programado_nombre, op.Apellido as programado_apellido,
            of.Nombre as finalizado_nombre, of.Apellido as finalizado_apellido
        FROM mtto_equipos_movimientos m
        INNER JOIN mtto_equipos e ON m.equipo_id = e.id
        INNER JOIN sucursales so ON m.sucursal_origen_id = so.id
        INNER JOIN sucursales sd ON m.sucursal_destino_id = sd.id
        LEFT JOIN Operarios op ON m.programado_por = op.CodOperario
        LEFT JOIN Operarios of ON m.finalizado_por = of.CodOperario
        ORDER BY 
            CASE m.estado WHEN 'agendado' THEN 1 ELSE 2 END,
            m.fecha_programada DESC
    ");
    
    // Equipos con solicitud pendiente
    $equiposConSolicitud = $db->fetchAll("
        SELECT 
            e.id as equipo_id, e.codigo, e.marca, e.modelo,
            s.id as solicitud_id, s.descripcion_problema,
            (SELECT suc.id 
             FROM mtto_equipos_movimientos mov 
             INNER JOIN sucursales suc ON mov.sucursal_destino_id = suc.id 
             WHERE mov.equipo_id = e.id AND mov.estado = 'finalizado' 
             ORDER BY mov.fecha_realizada DESC LIMIT 1) as sucursal_actual_id,
            (SELECT suc.nombre 
             FROM mtto_equipos_movimientos mov 
             INNER JOIN sucursales suc ON mov.sucursal_destino_id = suc.id 
             WHERE mov.equipo_id = e.id AND mov.estado = 'finalizado' 
             ORDER BY mov.fecha_realizada DESC LIMIT 1) as sucursal_actual
        FROM mtto_equipos e
        INNER JOIN mtto_equipos_solicitudes s ON e.id = s.equipo_id
        WHERE s.estado = 'solicitado'
        AND NOT EXISTS (
            SELECT 1 FROM mtto_equipos_movimientos mov
            WHERE mov.equipo_id = e.id 
            AND mov.estado = 'agendado'
            AND mov.sucursal_origen_id = (
                SELECT m2.sucursal_destino_id 
                FROM mtto_equipos_movimientos m2
                WHERE m2.equipo_id = e.id AND m2.estado = 'finalizado'
                ORDER BY m2.fecha_realizada DESC LIMIT 1
            )
        )
    ");
} else {
    // Líderes de sucursal solo ven movimientos de su sucursal
    $codigoSucursal = $sucursales[0]['codigo'];
    $sucursalId = $db->fetchOne("SELECT id FROM sucursales WHERE codigo = ?", [$codigoSucursal])['id'];
    
    $movimientos = $db->fetchAll("
        SELECT 
            m.*,
            e.codigo, e.marca, e.modelo,
            so.nombre as sucursal_origen,
            sd.nombre as sucursal_destino,
            op.Nombre as programado_nombre, op.Apellido as programado_apellido
        FROM mtto_equipos_movimientos m
        INNER JOIN mtto_equipos e ON m.equipo_id = e.id
        INNER JOIN sucursales so ON m.sucursal_origen_id = so.id
        INNER JOIN sucursales sd ON m.sucursal_destino_id = sd.id
        LEFT JOIN Operarios op ON m.programado_por = op.CodOperario
        WHERE (m.sucursal_origen_id = ? OR m.sucursal_destino_id = ?)
        AND m.estado = 'agendado'
        ORDER BY m.fecha_programada ASC
    ", [$sucursalId, $sucursalId]);
    
    $equiposConSolicitud = [];
}

// Obtener todas las sucursales para los selectores
$todasSucursales = $db->fetchAll("SELECT id, codigo, nombre FROM sucursales WHERE activa = 1 ORDER BY nombre");

// Obtener equipos disponibles en central
$equiposEnCentral = $db->fetchAll("
    SELECT 
        e.id, e.codigo, e.marca, e.modelo,
        t.nombre as tipo
    FROM mtto_equipos e
    INNER JOIN mtto_equipos_tipos t ON e.tipo_equipo_id = t.id
    WHERE e.activo = 1
    AND e.id IN (
        SELECT mov.equipo_id 
        FROM mtto_equipos_movimientos mov
        INNER JOIN sucursales s ON mov.sucursal_destino_id = s.id
        WHERE s.codigo = '0' AND mov.estado = 'finalizado'
        AND mov.id = (
            SELECT MAX(m2.id) 
            FROM mtto_equipos_movimientos m2 
            WHERE m2.equipo_id = mov.equipo_id AND m2.estado = 'finalizado'
        )
    )
    ORDER BY e.codigo
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Movimientos - Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_general.css">
    <style>
        .solicitudes-pendientes {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .solicitud-card {
            background: white;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .movimiento-row-agendado {
            background-color: #fff3cd;
        }
        
        .movimiento-row-finalizado {
            background-color: #d4edda;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="page-header">
            <h1 class="page-title">🚚 Gestión de Movimientos de Equipos</h1>
            <div>
                <?php if ($cargoOperario == 35): ?>
                <button class="btn btn-primary" onclick="abrirNuevoMovimiento()">
                    ➕ Nuevo Movimiento
                </button>
                <?php endif; ?>
                <a href="equipos_lista.php" class="btn btn-secondary ml-1">← Volver</a>
            </div>
        </div>

        <?php if ($cargoOperario == 35 && !empty($equiposConSolicitud)): ?>
        <div class="solicitudes-pendientes">
            <h3 style="color: #856404; margin-bottom: 15px;">⚠️ Equipos con Solicitud de Mantenimiento Pendiente</h3>
            <p style="margin-bottom: 15px;">Los siguientes equipos tienen solicitudes de mantenimiento sin movimiento programado:</p>
            
            <?php foreach ($equiposConSolicitud as $eq): ?>
            <div class="solicitud-card">
                <div class="d-flex justify-between align-center">
                    <div>
                        <strong>Equipo:</strong> <?= htmlspecialchars($eq['codigo']) ?> - <?= htmlspecialchars($eq['marca'] . ' ' . $eq['modelo']) ?><br>
                        <strong>Ubicación:</strong> <?= htmlspecialchars($eq['sucursal_actual']) ?><br>
                        <strong>Problema:</strong> <?= htmlspecialchars(substr($eq['descripcion_problema'], 0, 100)) ?>...
                    </div>
                    <button class="btn btn-warning" onclick="abrirMovimientoConSolicitud(<?= $eq['equipo_id'] ?>, <?= $eq['sucursal_actual_id'] ?>, <?= $eq['solicitud_id'] ?>)">
                        📦 Crear Movimiento
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Estado</th>
                            <th>Equipo</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Fecha Programada</th>
                            <?php if ($cargoOperario == 35): ?>
                            <th>Programado Por</th>
                            <?php endif; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                        <tr class="movimiento-row-<?= $mov['estado'] ?>">
                            <td><?= $mov['id'] ?></td>
                            <td>
                                <?php if ($mov['estado'] == 'agendado'): ?>
                                    <span class="badge badge-warning">Agendado</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Finalizado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($mov['codigo']) ?></strong><br>
                                <small><?= htmlspecialchars($mov['marca'] . ' ' . $mov['modelo']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($mov['sucursal_origen']) ?></td>
                            <td><?= htmlspecialchars($mov['sucursal_destino']) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($mov['fecha_programada'])) ?>
                                <?php if ($mov['fecha_realizada']): ?>
                                    <br><small>Realizado: <?= date('d/m/Y', strtotime($mov['fecha_realizada'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <?php if ($cargoOperario == 35): ?>
                            <td><?= htmlspecialchars($mov['programado_nombre'] . ' ' . $mov['programado_apellido']) ?></td>
                            <?php endif; ?>
                            <td>
                                <?php if ($mov['estado'] == 'agendado'): ?>
                                    <?php if ($cargoOperario == 35 || 
                                        ($cargoOperario == 5 || $cargoOperario == 43)): ?>
                                    <button class="btn btn-sm btn-success" onclick="finalizarMovimiento(<?= $mov['id'] ?>)">
                                        ✓ Finalizar
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Completado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para nuevo movimiento -->
    <div id="modal-movimiento" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2 class="modal-title">Programar Movimiento de Equipo</h2>
                <button class="modal-close" onclick="closeModal('modal-movimiento')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-movimiento" onsubmit="guardarMovimiento(event)">
                    <input type="hidden" id="mov-solicitud-id" name="solicitud_id">
                    
                    <div class="form-group">
                        <label class="form-label required">Equipo a Mover</label>
                        <select name="equipo_id" id="mov-equipo-id" class="form-control" required onchange="actualizarOrigenDestino()">
                            <option value="">Seleccione equipo...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Sucursal Origen</label>
                        <select name="sucursal_origen_id" id="mov-origen" class="form-control" required>
                            <?php foreach ($todasSucursales as $suc): ?>
                            <option value="<?= $suc['id'] ?>"><?= htmlspecialchars($suc['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Sucursal Destino</label>
                        <select name="sucursal_destino_id" id="mov-destino" class="form-control" required>
                            <?php foreach ($todasSucursales as $suc): ?>
                            <option value="<?= $suc['id'] ?>"><?= htmlspecialchars($suc['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Fecha Programada</label>
                        <input type="date" name="fecha_programada" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3"></textarea>
                    </div>

                    <!-- Opción de enviar equipo de cambio -->
                    <div id="opcion-cambio" style="display: none;">
                        <hr>
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" id="enviar-cambio" onchange="toggleEquipoCambio()">
                                <span>Enviar equipo de cambio a esta sucursal</span>
                            </label>
                        </div>

                        <div id="equipo-cambio-container" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Equipo de Reemplazo (desde Central)</label>
                                <select name="equipo_cambio_id" id="equipo-cambio" class="form-control">
                                    <option value="">Seleccione equipo...</option>
                                    <?php foreach ($equiposEnCentral as $eq): ?>
                                    <option value="<?= $eq['id'] ?>">
                                        <?= htmlspecialchars($eq['codigo'] . ' - ' . $eq['marca'] . ' ' . $eq['modelo']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('modal-movimiento')">Cancelar</button>
                <button class="btn btn-primary" onclick="document.getElementById('form-movimiento').requestSubmit()">
                    💾 Programar Movimiento(s)
                </button>
            </div>
        </div>
    </div>

    <script src="js/equipos_funciones.js"></script>
    <script>
        const equiposData = <?= json_encode($db->fetchAll("
            SELECT 
                e.id, e.codigo, e.marca, e.modelo,
                (SELECT s.id FROM mtto_equipos_movimientos m 
                 INNER JOIN sucursales s ON m.sucursal_destino_id = s.id 
                 WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
                 ORDER BY m.fecha_realizada DESC LIMIT 1) as ubicacion_actual_id
            FROM mtto_equipos e
            WHERE e.activo = 1
        ")) ?>;

        function abrirNuevoMovimiento() {
            document.getElementById('form-movimiento').reset();
            document.getElementById('mov-solicitud-id').value = '';
            document.getElementById('opcion-cambio').style.display = 'none';
            cargarEquipos();
            openModal('modal-movimiento');
        }

        function abrirMovimientoConSolicitud(equipoId, sucursalOrigenId, solicitudId) {
            document.getElementById('form-movimiento').reset();
            document.getElementById('mov-solicitud-id').value = solicitudId;
            document.getElementById('opcion-cambio').style.display = 'block';
            
            cargarEquipos(equipoId);
            document.getElementById('mov-origen').value = sucursalOrigenId;
            
            // Destino por defecto: central (código 0)
            const central = document.querySelector('#mov-destino option[value]');
            if (central) document.getElementById('mov-destino').value = central.value;
            
            openModal('modal-movimiento');
        }

        function cargarEquipos(selectedId = null) {
            const select = document.getElementById('mov-equipo-id');
            select.innerHTML = '<option value="">Seleccione equipo...</option>';
            
            equiposData.forEach(eq => {
                const option = document.createElement('option');
                option.value = eq.id;
                option.textContent = `${eq.codigo} - ${eq.marca} ${eq.modelo}`;
                option.dataset.ubicacionId = eq.ubicacion_actual_id;
                if (selectedId && eq.id == selectedId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            
            if (selectedId) actualizarOrigenDestino();
        }

        function actualizarOrigenDestino() {
            const select = document.getElementById('mov-equipo-id');
            const option = select.options[select.selectedIndex];
            const ubicacionId = option.dataset.ubicacionId;
            
            if (ubicacionId) {
                document.getElementById('mov-origen').value = ubicacionId;
            }
        }

        function toggleEquipoCambio() {
            const checked = document.getElementById('enviar-cambio').checked;
            const container = document.getElementById('equipo-cambio-container');
            container.style.display = checked ? 'block' : 'none';
            
            if (!checked) {
                document.getElementById('equipo-cambio').value = '';
            }
        }

        function guardarMovimiento(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            showLoading(true);
            
            fetch('ajax/equipos_movimiento_crear.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                showLoading(false);
                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal('modal-movimiento');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Error al crear movimiento', 'danger');
                }
            })
            .catch(error => {
                showLoading(false);
                console.error('Error:', error);
                showAlert('Error al procesar la solicitud', 'danger');
            });
        }

        function finalizarMovimiento(movimientoId) {
            if (!confirm('¿Confirmar que el movimiento se ha realizado?')) {
                return;
            }
            
            showLoading(true);
            
            fetch('ajax/equipos_movimiento_finalizar.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({movimiento_id: movimientoId})
            })
            .then(response => response.json())
            .then(result => {
                showLoading(false);
                if (result.success) {
                    showAlert('Movimiento finalizado', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message || 'Error al finalizar movimiento', 'danger');
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