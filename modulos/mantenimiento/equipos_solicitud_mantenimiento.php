<?php
// public_html/modulos/mantenimiento/equipos_solicitud_mantenimiento.php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once 'config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
$codigo_sucursal_busqueda = $sucursales[0]['nombre'];

// Verificar permisos
if (!in_array($cargoOperario, [5, 43, 35])) {
    die("Acceso denegado");
}

$equipo_id = $_GET['equipo_id'] ?? 0;

// Obtener información del equipo
$stmt = $db->query("
    SELECT e.*, et.nombre as tipo_nombre,
        (
            SELECT CASE 
                WHEN m.destino_tipo = 'Central' THEN 'Almacén Central'
                WHEN m.destino_tipo = 'Sucursal' THEN s.nombre
                WHEN m.destino_tipo = 'Proveedor' THEN CONCAT('Proveedor: ', m.proveedor_nombre)
                ELSE 'Sin ubicación'
            END
            FROM mtto_equipos_movimientos m
            LEFT JOIN sucursales s ON m.destino_id = s.id AND m.destino_tipo = 'Sucursal'
            WHERE m.equipo_id = e.id 
                AND m.estado = 'Completado'
            ORDER BY m.fecha_ejecutada DESC, m.id DESC
            LIMIT 1
        ) as ubicacion_actual
    FROM mtto_equipos e
    INNER JOIN mtto_equipos_tipos et ON e.tipo_id = et.id
    WHERE e.id = :equipo_id AND e.activo = 1
", ['equipo_id' => $equipo_id]);

$equipo = $stmt->fetch();

if (!$equipo) {
    die("Equipo no encontrado");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Mantenimiento - <?php echo htmlspecialchars($equipo['codigo']); ?></title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Solicitud de Mantenimiento Correctivo</h1>
                <a href="equipos_lista.php" class="btn btn-secundario">← Volver</a>
            </div>

            <div class="alerta alerta-info">
                <strong>Equipo:</strong> <?php echo htmlspecialchars($equipo['codigo'] . ' - ' . $equipo['nombre']); ?><br>
                <strong>Tipo:</strong> <?php echo htmlspecialchars($equipo['tipo_nombre']); ?><br>
                <strong>Ubicación:</strong> <?php echo htmlspecialchars($equipo['ubicacion_actual']); ?>
            </div>

            <form id="formSolicitud" class="formulario" enctype="multipart/form-data">
                <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                <input type="hidden" name="solicitado_por" value="<?php echo $_SESSION['usuario_id']; ?>">
                
                <div class="grupo-formulario">
                    <label class="campo-requerido">Descripción del Problema</label>
                    <textarea name="descripcion_problema" required 
                              placeholder="Describa detalladamente el problema que presenta el equipo..."></textarea>
                </div>

                <div class="grupo-formulario">
                    <label class="campo-requerido">Evidencias Fotográficas / Archivos</label>
                    <div class="zona-archivos">
                        <p>Adjunte fotos o documentos del problema (mínimo 1 archivo requerido)</p>
                        <div class="botones-subida">
                            <button type="button" class="btn btn-primario" onclick="abrirArchivos()">
                                📁 Seleccionar Archivos
                            </button>
                            <button type="button" class="btn btn-secundario" onclick="abrirCamara()">
                                📷 Tomar Foto
                            </button>
                        </div>
                        <input type="file" id="inputArchivos" multiple accept="image/*,.pdf,.doc,.docx" style="display:none;">
                        <input type="file" id="inputCamara" capture="environment" accept="image/*" style="display:none;">
                    </div>
                    
                    <div class="previsualizacion-archivos" id="previsualizacion"></div>
                </div>

                <div class="grupo-formulario">
                    <label>Observaciones Adicionales</label>
                    <textarea name="observaciones" 
                              placeholder="Cualquier información adicional relevante..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primario" id="btnEnviar">
                        Enviar Solicitud
                    </button>
                    <a href="equipos_lista.php" class="btn btn-secundario">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/equipos_solicitud.js"></script>
</body>
</html>