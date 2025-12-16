<?php
// public_html/modulos/mantenimiento/equipos_repuestos.php (Gestión de repuestos)
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once 'config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Solo líder de infraestructura
if ($cargoOperario != 35) {
    die("Acceso denegado");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Repuestos</title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Gestión de Repuestos</h1>
                <div>
                    <button class="btn btn-primario" onclick="abrirModalRepuesto()">+ Nuevo Repuesto</button>
                    <a href="equipos_lista.php" class="btn btn-secundario">← Volver</a>
                </div>
            </div>

            <div class="tabla-contenedor">
                <table class="tabla-equipos" id="tablaRepuestos">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Costo Base</th>
                            <th>Unidad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="texto-centrado">
                                <div class="loading"></div> Cargando repuestos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para repuesto -->
    <div class="modal" id="modalRepuesto">
        <div class="modal-contenido">
            <span class="modal-cerrar" onclick="cerrarModalRepuesto()">&times;</span>
            <h2 style="color: #0E544C; margin-bottom: 20px;" id="tituloModal">Nuevo Repuesto</h2>
            
            <form id="formRepuesto">
                <input type="hidden" name="repuesto_id" id="repuesto_id">
                
                <div class="grupo-formulario">
                    <label class="campo-requerido">Nombre del Repuesto</label>
                    <input type="text" name="nombre" id="nombre_repuesto" required 
                           placeholder="Ej: Disco Duro 1TB">
                </div>

                <div class="grupo-formulario">
                    <label>Descripción</label>
                    <textarea name="descripcion" id="descripcion_repuesto"
                              placeholder="Descripción detallada del repuesto..."></textarea>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Costo Base</label>
                        <input type="number" name="costo_base" id="costo_base_repuesto" 
                               step="0.01" min="0" required placeholder="0.00">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label>Unidad de Medida</label>
                        <input type="text" name="unidad_medida" id="unidad_repuesto" 
                               placeholder="Ej: Unidad, Metros, Litros">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primario">Guardar</button>
                    <button type="button" class="btn btn-secundario" onclick="cerrarModalRepuesto()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/equipos_repuestos.js"></script>
    <script>
        const usuarioId = <?php echo $_SESSION['usuario_id']; ?>;
    </script>
</body>
</html>
?>