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

$programado_id = $_GET['programado_id'] ?? null;
$equipo_id = $_GET['equipo_id'] ?? null;

if (!$programado_id || !$equipo_id) {
    header('Location: equipos_calendario.php');
    exit;
}

// Obtener datos del mantenimiento programado
$programado = $db->fetchOne(
    "SELECT mp.*, e.codigo, e.marca, e.modelo
     FROM mtto_equipos_mantenimientos_programados mp
     INNER JOIN mtto_equipos e ON mp.equipo_id = e.id
     WHERE mp.id = ?",
    [$programado_id]
);

if (!$programado) {
    header('Location: equipos_calendario.php');
    exit;
}

// Obtener proveedores
$proveedores = $db->fetchAll("SELECT id, nombre FROM proveedores_compras_servicios WHERE activo = 1 ORDER BY nombre");

// Obtener repuestos
$repuestos = $db->fetchAll("SELECT id, nombre, costo_base, unidad_medida FROM mtto_equipos_repuestos WHERE activo = 1 ORDER BY nombre");

// Obtener solicitud pendiente del equipo automáticamente
$solicitudPendiente = $db->fetchOne("
    SELECT id, descripcion_problema, fecha_solicitud
    FROM mtto_equipos_solicitudes
    WHERE equipo_id = ? AND estado = 'solicitado'
    ORDER BY fecha_solicitud DESC
    LIMIT 1
", [$equipo_id]);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Insertar mantenimiento
        $stmt = $db->query(
            "INSERT INTO mtto_equipos_mantenimientos (
                mantenimiento_programado_id, equipo_id, solicitud_id, tipo,
                proveedor_servicio_id, fecha_inicio, fecha_finalizacion,
                problema_encontrado, trabajo_realizado, observaciones,
                costo_total_repuestos, costo_mano_de_obra, registrado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $programado_id,
                $equipo_id,
                $solicitudPendiente['id'] ?? null,
                $programado['tipo'],
                $_POST['proveedor_servicio_id'] ?: null,
                $_POST['fecha_inicio'],
                $_POST['fecha_finalizacion'] ?: null,
                $_POST['problema_encontrado'],
                $_POST['trabajo_realizado'],
                $_POST['observaciones'],
                $_POST['costo_total_repuestos'],
                $_POST['costo_mano_de_obra'] ?? 0,
                $_SESSION['usuario_id']
            ]
        );
        
        $mantenimiento_id = $db->lastInsertId();
        
        // Guardar repuestos
        if (!empty($_POST['repuestos'])) {
            foreach ($_POST['repuestos'] as $repuesto) {
                if (!empty($repuesto['id']) && !empty($repuesto['cantidad'])) {
                    $db->query(
                        "INSERT INTO mtto_equipos_mantenimientos_repuestos 
                         (mantenimiento_id, repuesto_id, cantidad, precio_unitario, precio_total)
                         VALUES (?, ?, ?, ?, ?)",
                        [
                            $mantenimiento_id,
                            $repuesto['id'],
                            $repuesto['cantidad'],
                            $repuesto['precio_unitario'],
                            $repuesto['precio_total']
                        ]
                    );
                }
            }
        }
        
        // Actualizar estado del programado
        $db->query(
            "UPDATE mtto_equipos_mantenimientos_programados 
             SET estado = 'finalizado' 
             WHERE id = ?",
            [$programado_id]
        );
        

        
        // Guardar imágenes si hay
        if (isset($_FILES['imagenes'])) {
            $uploadDir = '../uploads/mantenimientos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['imagenes']['error'][$key] === 0) {
                    $extension = pathinfo($_FILES['imagenes']['name'][$key], PATHINFO_EXTENSION);
                    $filename = 'mant_' . $mantenimiento_id . '_' . time() . '_' . $key . '.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $filepath)) {
                        $db->query(
                            "INSERT INTO mtto_equipos_mantenimientos_fotos (mantenimiento_id, ruta_archivo)
                             VALUES (?, ?)",
                            [$mantenimiento_id, $filepath]
                        );
                    }
                }
            }
        }
        
        $db->getConnection()->commit();
        
        $mensaje = "Reporte de mantenimiento guardado exitosamente";
        $tipo_mensaje = "success";
        $redirigir = true;
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $mensaje = "Error al guardar reporte: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_general.css">
    <style>
        .repuestos-container {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .repuesto-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            align-items: end;
        }
        
        .costo-referencia {
            font-size: clamp(10px, 1.5vw, 12px) !important;
            color: #666;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="page-header">
            <div>
                <h1 class="page-title">📝 Reporte de Mantenimiento</h1>
                <p style="color: #666; margin-top: 5px;">
                    Equipo: <strong><?= htmlspecialchars($programado['codigo']) ?></strong> - 
                    <?= htmlspecialchars($programado['marca'] . ' ' . $programado['modelo']) ?>
                </p>
            </div>
            <a href="equipos_calendario.php" class="btn btn-secondary">← Volver al Calendario</a>
        </div>

        <?php if (isset($mensaje)): ?>
        <div class="alert alert-<?= $tipo_mensaje ?>">
            <?= $mensaje ?>
            <?php if (isset($redirigir)): ?>
            <script>
                setTimeout(() => {
                    window.location.href = 'equipos_calendario.php';
                }, 2000);
            </script>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" id="form-reporte">
                <div class="form-group">
                    <label class="form-label">Tipo de Mantenimiento</label>
                    <input type="text" class="form-control" value="<?= ucfirst($programado['tipo']) ?>" readonly>
                </div>

                <?php if ($solicitudPendiente): ?>
                <div class="alert alert-info">
                    <strong>📋 Solicitud Vinculada:</strong> Este mantenimiento se vinculará automáticamente con la solicitud 
                    #<?= $solicitudPendiente['id'] ?> del <?= date('d/m/Y', strtotime($solicitudPendiente['fecha_solicitud'])) ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Proveedor de Servicio</label>
                    <select name="proveedor_servicio_id" class="form-control">
                        <option value="">Seleccione...</option>
                        <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= $prov['id'] ?>"><?= $prov['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label required">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" 
                           value="<?= $programado['fecha_programada'] ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Fecha Finalización</label>
                    <input type="date" name="fecha_finalizacion" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Problema Encontrado</label>
                    <textarea name="problema_encontrado" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label required">Trabajo Realizado</label>
                    <textarea name="trabajo_realizado" class="form-control" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3"></textarea>
                </div>

                <!-- Repuestos -->
                <div class="form-group">
                    <label class="form-label">Repuestos Utilizados</label>
                    <div class="repuestos-container" id="repuestos-container">
                        <div id="repuestos-lista"></div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="agregarRepuesto()">
                            ➕ Agregar Repuesto
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Costo Mano de Obra</label>
                    <input type="number" step="0.01" name="costo_mano_de_obra" 
                           id="costo-mano-obra" class="form-control" value="0.00" 
                           onchange="calcularCostoTotal()">
                </div>

                <div class="form-group">
                    <label class="form-label">Costo Total Repuestos</label>
                    <input type="number" step="0.01" name="costo_total_repuestos" 
                           id="costo-total-repuestos" class="form-control" value="0.00" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: bold; color: #0E544C;">Costo Total del Mantenimiento</label>
                    <input type="text" id="costo-total-final" class="form-control" 
                           style="font-weight: bold; font-size: 18px; background: #e8f5f3;" readonly>
                </div>

                <!-- Evidencias fotográficas -->
                <div class="form-group">
                    <label class="form-label">Evidencias Fotográficas</label>
                    <div class="upload-container" id="upload-reporte">
                        <div class="upload-buttons">
                            <button type="button" class="btn btn-primary btn-upload-file">
                                📁 Subir Archivo
                            </button>
                            <button type="button" class="btn btn-primary btn-upload-camera">
                                📷 Tomar Foto
                            </button>
                        </div>
                        <input type="file" class="file-input" name="imagenes[]" accept="image/*" multiple>
                        <div class="preview-container"></div>
                    </div>
                </div>

                <div class="form-group mt-2">
                    <button type="submit" class="btn btn-success">💾 Guardar Reporte y Finalizar</button>
                    <a href="equipos_calendario.php" class="btn btn-secondary ml-1">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/equipos_funciones.js"></script>
    <script>
        const repuestosData = <?= json_encode($repuestos) ?>;
        let contadorRepuestos = 0;

        document.addEventListener('DOMContentLoaded', function() {
            initFileUpload('upload-reporte');
        });

        function agregarRepuesto() {
            contadorRepuestos++;
            const div = document.createElement('div');
            div.className = 'repuesto-item';
            div.id = 'repuesto-' + contadorRepuestos;
            div.innerHTML = `
                <div>
                    <select name="repuestos[${contadorRepuestos}][id]" class="form-control repuesto-select" 
                            onchange="actualizarCostoBase(this, ${contadorRepuestos})" required>
                        <option value="">Seleccione repuesto...</option>
                        ${repuestosData.map(r => `<option value="${r.id}" data-costo="${r.costo_base}">${r.nombre}</option>`).join('')}
                    </select>
                    <div class="costo-referencia" id="costo-ref-${contadorRepuestos}"></div>
                </div>
                <div>
                    <input type="number" step="0.01" name="repuestos[${contadorRepuestos}][cantidad]" 
                           class="form-control" placeholder="Cantidad" min="0.01" 
                           onchange="calcularTotalRepuesto(${contadorRepuestos})" required>
                </div>
                <div>
                    <input type="number" step="0.01" name="repuestos[${contadorRepuestos}][precio_unitario]" 
                           class="form-control" placeholder="Precio Unit." min="0" 
                           id="precio-unit-${contadorRepuestos}"
                           onchange="calcularTotalRepuesto(${contadorRepuestos})" required>
                </div>
                <div>
                    <input type="number" step="0.01" name="repuestos[${contadorRepuestos}][precio_total]" 
                           class="form-control" placeholder="Total" 
                           id="precio-total-${contadorRepuestos}" readonly>
                </div>
                <div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarRepuesto(${contadorRepuestos})">
                        🗑️
                    </button>
                </div>
            `;
            
            document.getElementById('repuestos-lista').appendChild(div);
        }

        function actualizarCostoBase(select, id) {
            const option = select.options[select.selectedIndex];
            const costoBase = option.dataset.costo || 0;
            const refDiv = document.getElementById('costo-ref-' + id);
            const precioInput = document.getElementById('precio-unit-' + id);
            
            if (costoBase > 0) {
                refDiv.textContent = `Costo base de referencia: C$ ${parseFloat(costoBase).toFixed(2)}`;
                precioInput.value = costoBase;
                calcularTotalRepuesto(id);
            } else {
                refDiv.textContent = '';
            }
        }

        function calcularTotalRepuesto(id) {
            const cantidad = document.querySelector(`[name="repuestos[${id}][cantidad]"]`).value;
            const precioUnit = document.getElementById('precio-unit-' + id).value;
            
            if (cantidad && precioUnit) {
                const total = parseFloat(cantidad) * parseFloat(precioUnit);
                document.getElementById('precio-total-' + id).value = total.toFixed(2);
                calcularCostoTotal();
            }
        }

        function calcularCostoTotal() {
            let totalRepuestos = 0;
            document.querySelectorAll('[id^="precio-total-"]').forEach(input => {
                const valor = parseFloat(input.value) || 0;
                totalRepuestos += valor;
            });
            document.getElementById('costo-total-repuestos').value = totalRepuestos.toFixed(2);
            
            // Calcular total final
            const manoObra = parseFloat(document.getElementById('costo-mano-obra').value) || 0;
            const totalFinal = totalRepuestos + manoObra;
            document.getElementById('costo-total-final').value = 'C$ ' + totalFinal.toFixed(2);
        }

        function eliminarRepuesto(id) {
            document.getElementById('repuesto-' + id).remove();
            calcularCostoTotal();
        }
    </script>
</body>
</html>