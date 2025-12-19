<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once __DIR__ . '/config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
$codigo_sucursal_busqueda = $sucursales[0]['nombre'];

// Solo líder de infraestructura puede registrar
if ($cargoOperario != 35 || $cargoOperario != 14) {
    header('Location: equipos_lista.php');
    exit;
}

// Obtener catálogos
$tipos = $db->fetchAll("SELECT * FROM mtto_equipos_tipos WHERE activo = 1 ORDER BY nombre");
$proveedores = $db->fetchAll("SELECT * FROM proveedores_compras_servicios WHERE activo = 1 ORDER BY nombre");

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Validar que el código no exista
        $existe = $db->fetchOne("SELECT id FROM mtto_equipos WHERE codigo = ?", [$_POST['codigo']]);
        if ($existe) {
            throw new Exception('Ya existe un equipo con ese código');
        }
        
        // Insertar equipo
        $db->query(
            "INSERT INTO mtto_equipos (
                codigo, tipo_equipo_id, marca, modelo, serial, 
                caracteristicas, fecha_compra, proveedor_compra_id, 
                garantia_meses, frecuencia_mantenimiento_meses, notas, registrado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_POST['codigo'],
                $_POST['tipo_equipo_id'],
                $_POST['marca'] ?? '',
                $_POST['modelo'] ?? '',
                $_POST['serial'] ?? '',
                $_POST['caracteristicas'] ?? '',
                $_POST['fecha_compra'] ?? null,
                $_POST['proveedor_compra_id'] ?: null,
                $_POST['garantia_meses'] ?? 0,
                $_POST['frecuencia_mantenimiento_meses'],
                $_POST['notas'] ?? '',
                $_SESSION['usuario_id']
            ]
        );
        
        $equipo_id = $db->lastInsertId();
        
        // Crear movimiento inicial a almacén central (sucursal código 0)
        $codigo_central = '0';
        
        $db->query(
            "INSERT INTO mtto_equipos_movimientos 
             (equipo_id, sucursal_origen_id, sucursal_destino_id, fecha_programada, 
              fecha_realizada, estado, observaciones, programado_por, finalizado_por)
             VALUES (?, ?, ?, NOW(), NOW(), 'finalizado', 'Registro inicial en almacén central', ?, ?)",
            [$equipo_id, $codigo_central, $codigo_central, $_SESSION['usuario_id'], $_SESSION['usuario_id']]
        );
        
        $db->getConnection()->commit();
        
        $mensaje = "Equipo registrado exitosamente con código: " . $_POST['codigo'];
        $tipo_mensaje = "success";
        $redirect = true;
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $mensaje = "Error al registrar equipo: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Equipo - Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_general.css">
</head>
<body>
    <div class="container-main">
        <?php if (isset($mensaje)): ?>
        <div class="alert alert-<?= $tipo_mensaje ?>">
            <?= $mensaje ?>
            <?php if (isset($redirect)): ?>
            <script>
                setTimeout(() => {
                    window.location.href = 'equipos_lista.php';
                }, 2000);
            </script>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" id="form-equipo" onsubmit="return validarFormulario()">
                <h3 style="color: #0E544C; margin-bottom: 20px;">📋 Información Básica</h3>
                
                <div class="form-group">
                    <label class="form-label required">Código del Equipo</label>
                    <input type="text" name="codigo" class="form-control" required 
                           placeholder="Ej: EQ-001, PC-LAB-05">
                    <small style="color: #666;">El código debe ser único e identificable</small>
                </div>

                <div class="form-group">
                    <label class="form-label required">Tipo de Equipo</label>
                    <select name="tipo_equipo_id" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($tipos as $tipo): ?>
                        <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Marca</label>
                        <input type="text" name="marca" class="form-control" 
                               placeholder="Ej: Dell, HP, Canon">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="modelo" class="form-control" 
                               placeholder="Ej: Latitude 5520">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Número de Serie</label>
                    <input type="text" name="serial" class="form-control" 
                           placeholder="Serial del fabricante">
                </div>

                <div class="form-group">
                    <label class="form-label">Características Técnicas</label>
                    <textarea name="caracteristicas" class="form-control" rows="3" 
                              placeholder="Especificaciones técnicas, capacidades, etc."></textarea>
                </div>

                <hr style="margin: 30px 0; border-color: #e0e0e0;">
                <h3 style="color: #0E544C; margin-bottom: 20px;">🛒 Información de Compra</h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Compra</label>
                        <input type="date" name="fecha_compra" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Proveedor</label>
                        <select name="proveedor_compra_id" class="form-control">
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $prov): ?>
                            <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Garantía (meses)</label>
                    <input type="number" name="garantia_meses" class="form-control" 
                           value="0" min="0" max="120" 
                           placeholder="Meses de garantía del fabricante">
                </div>

                <hr style="margin: 30px 0; border-color: #e0e0e0;">
                <h3 style="color: #0E544C; margin-bottom: 20px;">🔧 Configuración de Mantenimiento</h3>

                <div class="form-group">
                    <label class="form-label required">Frecuencia de Mantenimiento Preventivo (meses)</label>
                    <input type="number" name="frecuencia_mantenimiento_meses" class="form-control" 
                           value="3" min="1" max="24" required>
                    <small style="color: #666;">
                        Indica cada cuántos meses requiere mantenimiento preventivo. 
                        Por defecto: 3 meses (trimestral)
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Notas Adicionales</label>
                    <textarea name="notas" class="form-control" rows="4" 
                              placeholder="Información adicional, observaciones, historial previo, etc."></textarea>
                </div>

                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>📍 Nota:</strong> El equipo se registrará automáticamente en el 
                    <strong>Almacén Central</strong> con estado disponible.
                </div>

                <div class="form-group mt-2" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <a href="equipos_lista.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">💾 Guardar Equipo</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/equipos_funciones.js"></script>
    <script>
        function validarFormulario() {
            const codigo = document.querySelector('[name="codigo"]').value.trim();
            const tipo = document.querySelector('[name="tipo_equipo_id"]').value;
            const frecuencia = document.querySelector('[name="frecuencia_mantenimiento_meses"]').value;
            
            if (!codigo) {
                showAlert('El código del equipo es obligatorio', 'warning');
                return false;
            }
            
            if (!tipo) {
                showAlert('Debe seleccionar un tipo de equipo', 'warning');
                return false;
            }
            
            if (frecuencia < 1 || frecuencia > 24) {
                showAlert('La frecuencia de mantenimiento debe estar entre 1 y 24 meses', 'warning');
                return false;
            }
            
            return true;
        }

        // Convertir código a mayúsculas automáticamente
        document.querySelector('[name="codigo"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase().replace(/\s+/g, '-');
        });
    </script>
</body>
</html>