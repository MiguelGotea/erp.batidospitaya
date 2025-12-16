<?php
// public_html/modulos/mantenimiento/equipos_calendario.php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once 'config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Solo líder de infraestructura
if ($cargoOperario != 35) {
    die("Acceso denegado");
}

$mes = $_GET['mes'] ?? date('n');
$anio = $_GET['anio'] ?? date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
    <style>
        .equipo-sidebar {
            font-size: 12px;
        }
        .equipo-calendario {
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Calendario de Mantenimiento</h1>
                <a href="equipos_lista.php" class="btn btn-secundario">← Volver</a>
            </div>

            <div class="contenedor-calendario">
                <!-- Sidebar de equipos -->
                <div class="sidebar-equipos">
                    <h3 style="color: #0E544C; margin-bottom: 15px;">Equipos para Agendar</h3>
                    
                    <div style="margin-bottom: 20px;">
                        <input type="text" id="buscarEquipo" class="filtro-busqueda" 
                               placeholder="Buscar equipo...">
                    </div>

                    <div style="margin-bottom: 15px;">
                        <strong style="color: #dc3545;">● Retrasados</strong><br>
                        <strong style="color: #ffc107;">● Correctivos</strong><br>
                        <strong style="color: #51B8AC;">● Preventivos del mes</strong>
                    </div>

                    <div id="listaEquipos">
                        <div class="loading"></div> Cargando...
                    </div>
                </div>

                <!-- Calendario principal -->
                <div class="calendario-principal">
                    <div class="calendario-header">
                        <button class="btn btn-secundario" onclick="cambiarMes(-1)">◄ Anterior</button>
                        <h2 id="mesAnio" style="color: #0E544C; margin: 0;"></h2>
                        <button class="btn btn-secundario" onclick="cambiarMes(1)">Siguiente ►</button>
                    </div>

                    <div class="calendario-grid" id="calendarioGrid">
                        <div class="loading"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para movimiento -->
    <div class="modal" id="modalMovimiento">
        <div class="modal-contenido">
            <span class="modal-cerrar" onclick="cerrarModal()">&times;</span>
            <h2 style="color: #0E544C; margin-bottom: 20px;">Agendar Movimiento de Equipo</h2>
            
            <form id="formMovimiento">
                <input type="hidden" name="solicitud_id" id="solicitud_id">
                <input type="hidden" name="registrado_por" value="<?php echo $_SESSION['usuario_id']; ?>">
                
                <div id="infoEquipoMovimiento" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>

                <div class="grupo-formulario">
                    <label class="campo-requerido">Equipo a Enviar a Sucursal</label>
                    <select name="equipo_enviar_id" id="equipo_enviar_id" required>
                        <option value="">Seleccione un equipo del almacén central</option>
                    </select>
                </div>

                <div class="grupo-formulario">
                    <label class="campo-requerido">Fecha Planificada del Movimiento</label>
                    <input type="date" name="fecha_movimiento" id="fecha_movimiento" required>
                </div>

                <div class="grupo-formulario">
                    <label>Observaciones</label>
                    <textarea name="observaciones_movimiento" placeholder="Observaciones del movimiento..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primario">Agendar Movimiento</button>
                    <button type="button" class="btn btn-secundario" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/equipos_calendario.js"></script>
    <script>
        let mesActual = <?php echo $mes; ?>;
        let anioActual = <?php echo $anio; ?>;
        const usuarioId = <?php echo $_SESSION['usuario_id']; ?>;
    </script>
</body>
</html>