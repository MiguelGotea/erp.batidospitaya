<?php
// public_html/modulos/mantenimiento/equipos_movimientos_pendientes.php
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
    <title>Movimientos Pendientes</title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Movimientos Pendientes</h1>
                <a href="equipos_lista.php" class="btn btn-secundario">← Volver</a>
            </div>

            <div class="tabla-contenedor">
                <table class="tabla-equipos" id="tablaMovimientos">
                    <thead>
                        <tr>
                            <th>Equipo</th>
                            <th>Tipo Movimiento</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Fecha Planificada</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="texto-centrado">
                                <div class="loading"></div> Cargando movimientos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para ejecutar movimiento -->
    <div class="modal" id="modalEjecutar">
        <div class="modal-contenido">
            <span class="modal-cerrar" onclick="cerrarModalEjecutar()">&times;</span>
            <h2 style="color: #0E544C; margin-bottom: 20px;">Ejecutar Movimiento</h2>
            
            <form id="formEjecutar">
                <input type="hidden" name="movimiento_id" id="movimiento_id">
                <input type="hidden" name="registrado_por" value="<?php echo $_SESSION['usuario_id']; ?>">
                
                <div id="infoMovimiento" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>

                <div class="grupo-formulario">
                    <label class="campo-requerido">Fecha de Ejecución</label>
                    <input type="date" name="fecha_ejecutada" id="fecha_ejecutada" required>
                </div>

                <div class="grupo-formulario">
                    <label>Observaciones</label>
                    <textarea name="observaciones_ejecucion" 
                              placeholder="Observaciones sobre la ejecución del movimiento..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primario">Marcar como Completado</button>
                    <button type="button" class="btn btn-secundario" onclick="cerrarModalEjecutar()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/equipos_movimientos.js"></script>
    <script>
        const usuarioId = <?php echo $_SESSION['usuario_id']; ?>;
    </script>
</body>
</html>