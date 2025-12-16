<?php
// public_html/modulos/mantenimiento/equipos_reporte_mantenimiento.php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once 'config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Solo líder de infraestructura
if ($cargoOperario != 35) {
    die("Acceso denegado");
}

$mantenimiento_id = $_GET['id'] ?? 0;

// Obtener información del mantenimiento
$mantenimiento = $db->fetchOne("
    SELECT 
        mt.*,
        e.codigo as equipo_codigo,
        e.nombre as equipo_nombre,
        et.nombre as equipo_tipo
    FROM mtto_equipos_mantenimientos mt
    INNER JOIN mtto_equipos e ON mt.equipo_id = e.id
    INNER JOIN mtto_equipos_tipos et ON e.tipo_id = et.id
    WHERE mt.id = :id
", ['id' => $mantenimiento_id]);

if (!$mantenimiento) {
    die("Mantenimiento no encontrado");
}

// Obtener repuestos disponibles
$repuestos = $db->fetchAll("
    SELECT id, nombre, descripcion, costo_base, unidad_medida
    FROM mtto_equipos_repuestos
    WHERE activo = 1
    ORDER BY nombre
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Reporte de Mantenimiento</h1>
                <a href="equipos_calendario.php" class="btn btn-secundario">← Volver</a>
            </div>

            <div class="alerta alerta-info">
                <strong>Equipo:</strong> <?php echo htmlspecialchars($mantenimiento['equipo_codigo'] . ' - ' . $mantenimiento['equipo_nombre']); ?><br>
                <strong>Tipo:</strong> <?php echo htmlspecialchars($mantenimiento['equipo_tipo']); ?><br>
                <strong>Tipo de Mantenimiento:</strong> 
                <span class="badge badge-<?php echo $mantenimiento['tipo_mantenimiento'] == 'Preventivo' ? 'programado' : 'solicitado'; ?>">
                    <?php echo htmlspecialchars($mantenimiento['tipo_mantenimiento']); ?>
                </span><br>
                <strong>Fecha Programada:</strong> <?php echo htmlspecialchars($mantenimiento['fecha_programada']); ?>
            </div>

            <form id="formReporte" class="formulario">
                <input type="hidden" name="mantenimiento_id" value="<?php echo $mantenimiento_id; ?>">
                <input type="hidden" name="registrado_por" value="<?php echo $_SESSION['usuario_id']; ?>">
                
                <h3 style="color: #0E544C; margin-bottom: 20px;">Información del Servicio</h3>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Fecha de Realización</label>
                        <input type="date" name="fecha_realizada" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Proveedor de Servicio</label>
                        <input type="text" name="proveedor_servicio" required 
                               placeholder="Nombre del proveedor">
                    </div>
                </div>

                <div class="grupo-formulario">
                    <label class="campo-requerido">Problema Encontrado</label>
                    <textarea name="problema_encontrado" required 
                              placeholder="Describa el problema que fue encontrado por el técnico..."></textarea>
                </div>

                <div class="grupo-formulario">
                    <label class="campo-requerido">Trabajo Realizado</label>
                    <textarea name="trabajo_realizado" required 
                              placeholder="Describa detalladamente el trabajo que se realizó..."></textarea>
                </div>

                <h3 style="color: #0E544C; margin: 30px 0 20px;">Repuestos Utilizados</h3>

                <div style="margin-bottom: 20px;">
                    <button type="button" class="btn btn-primario" onclick="agregarRepuesto()">
                        + Agregar Repuesto
                    </button>
                </div>

                <div id="listaRepuestos"></div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <strong>Total Repuestos: $<span id="totalRepuestos">0.00</span></strong>
                </div>

                <h3 style="color: #0E544C; margin: 30px 0 20px;">Costos</h3>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Costo Mano de Obra</label>
                        <input type="number" name="costo_mano_obra" id="costo_mano_obra" 
                               step="0.01" min="0" required value="0"
                               onchange="calcularTotal()">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label>Costo Total</label>
                        <input type="number" name="costo_total" id="costo_total" 
                               step="0.01" min="0" readonly 
                               style="background: #f8f9fa; font-weight: bold;">
                    </div>
                </div>

                <h3 style="color: #0E544C; margin: 30px 0 20px;">Evidencias</h3>

                <div class="grupo-formulario">
                    <label>Fotos / Archivos del Mantenimiento</label>
                    <div class="zona-archivos">
                        <p>Adjunte fotos o documentos del mantenimiento realizado</p>
                        <div class="botones-subida">
                            <button type="button" class="btn btn-primario" onclick="abrirArchivosReporte()">
                                📁 Seleccionar Archivos
                            </button>
                            <button type="button" class="btn btn-secundario" onclick="abrirCamaraReporte()">
                                📷 Tomar Foto
                            </button>
                        </div>
                        <input type="file" id="inputArchivosReporte" multiple accept="image/*,.pdf,.doc,.docx" style="display:none;">
                        <input type="file" id="inputCamaraReporte" capture="environment" accept="image/*" style="display:none;">
                    </div>
                    
                    <div class="previsualizacion-archivos" id="previsualizacionReporte"></div>
                </div>

                <div class="grupo-formulario">
                    <label>Observaciones Finales</label>
                    <textarea name="observaciones" 
                              placeholder="Cualquier observación adicional sobre el mantenimiento..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primario" id="btnGuardarReporte">
                        Guardar Reporte
                    </button>
                    <a href="equipos_calendario.php" class="btn btn-secundario">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Template de repuesto -->
    <template id="templateRepuesto">
        <div class="tarjeta repuesto-item" style="margin-bottom: 15px; border-left: 4px solid #51B8AC;">
            <div class="fila-formulario">
                <div class="grupo-formulario" style="flex: 2;">
                    <label>Repuesto</label>
                    <select class="select-repuesto" required onchange="seleccionarRepuesto(this)">
                        <option value="">Seleccione un repuesto</option>
                        <?php foreach ($repuestos as $rep): ?>
                            <option value="<?php echo $rep['id']; ?>" 
                                    data-costo="<?php echo $rep['costo_base']; ?>"
                                    data-unidad="<?php echo htmlspecialchars($rep['unidad_medida']); ?>">
                                <?php echo htmlspecialchars($rep['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grupo-formulario">
                    <label>Costo Base Ref.</label>
                    <input type="number" class="costo-base" step="0.01" readonly 
                           style="background: #f8f9fa;">
                </div>
                
                <div class="grupo-formulario">
                    <label>Cantidad</label>
                    <input type="number" class="cantidad-repuesto" step="0.01" min="0.01" 
                           required value="1" onchange="calcularTotal()">
                </div>
                
                <div class="grupo-formulario">
                    <label>Costo Real Unit.</label>
                    <input type="number" class="costo-real" step="0.01" min="0" 
                           required onchange="calcularTotal()">
                </div>
                
                <div class="grupo-formulario">
                    <label>Total</label>
                    <input type="number" class="total-repuesto" step="0.01" readonly 
                           style="background: #f8f9fa; font-weight: bold;">
                </div>
                
                <div style="display: flex; align-items: flex-end;">
                    <button type="button" class="btn btn-peligro btn-pequeno" 
                            onclick="eliminarRepuesto(this)">
                        🗑️
                    </button>
                </div>
            </div>
            
            <div class="grupo-formulario">
                <label>Observaciones del Repuesto</label>
                <textarea class="observaciones-repuesto" 
                          placeholder="Observaciones sobre el uso de este repuesto..."></textarea>
            </div>
        </div>
    </template>

    <script src="js/equipos_reporte.js"></script>
    <script>
        const repuestosDisponibles = <?php echo json_encode($repuestos); ?>;
    </script>
</body>
</html>