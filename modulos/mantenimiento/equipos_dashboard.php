<?php
// public_html/modulos/mantenimiento/equipos_dashboard.php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once 'config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$equipo_id = $_GET['id'] ?? 0;

if (!in_array($cargoOperario, [5, 43, 35])) {
    die("Acceso denegado");
}

// Obtener información del equipo
$equipo = $db->fetchOne("
    SELECT e.*, et.nombre as tipo_nombre
    FROM mtto_equipos e
    INNER JOIN mtto_equipos_tipos et ON e.tipo_id = et.id
    WHERE e.id = :id
", ['id' => $equipo_id]);

if (!$equipo) {
    die("Equipo no encontrado");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($equipo['codigo']); ?></title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Dashboard de Equipo</h1>
                <a href="equipos_lista.php" class="btn btn-secundario">← Volver</a>
            </div>

            <!-- Información del Equipo -->
            <div class="tarjeta" style="background: linear-gradient(135deg, #0E544C 0%, #51B8AC 100%); color: white;">
                <h2 style="color: white; margin-bottom: 15px;">
                    <?php echo htmlspecialchars($equipo['codigo'] . ' - ' . $equipo['nombre']); ?>
                </h2>
                <div class="fila-formulario">
                    <div>
                        <strong>Tipo:</strong> <?php echo htmlspecialchars($equipo['tipo_nombre']); ?>
                    </div>
                    <div>
                        <strong>Marca:</strong> <?php echo htmlspecialchars($equipo['marca'] ?? 'N/A'); ?>
                    </div>
                    <div>
                        <strong>Modelo:</strong> <?php echo htmlspecialchars($equipo['modelo'] ?? 'N/A'); ?>
                    </div>
                    <div>
                        <strong>Serial:</strong> <?php echo htmlspecialchars($equipo['serial'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>

            <!-- Estadísticas -->
            <h3 style="color: #0E544C; margin: 30px 0 20px;">Estadísticas Generales</h3>
            <div class="dashboard-grid" id="estadisticas">
                <div class="estadistica-card">
                    <div class="loading"></div>
                </div>
            </div>

            <!-- Plan de Mantenimiento -->
            <h3 style="color: #0E544C; margin: 30px 0 20px;">Plan de Mantenimiento</h3>
            <div class="tarjeta" id="planMantenimiento">
                <div class="loading"></div> Cargando...
            </div>

            <!-- Mantenimientos en Curso -->
            <h3 style="color: #0E544C; margin: 30px 0 20px;">Mantenimientos en Curso</h3>
            <div class="tarjeta" id="mantenimientosCurso">
                <div class="loading"></div> Cargando...
            </div>

            <!-- Historial de Mantenimientos -->
            <h3 style="color: #0E544C; margin: 30px 0 20px;">Historial de Mantenimientos</h3>
            <div class="tabla-contenedor">
                <table class="tabla-equipos" id="tablaMantenimientos">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Proveedor</th>
                            <th>Trabajo Realizado</th>
                            <th>Costo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="6" class="texto-centrado">
                                <div class="loading"></div> Cargando...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Historial de Movimientos -->
            <h3 style="color: #0E544C; margin: 30px 0 20px;">Historial de Movimientos</h3>
            <div class="timeline" id="timelineMovimientos">
                <div class="loading"></div> Cargando...
            </div>
        </div>
    </div>

    <script src="js/equipos_dashboard.js"></script>
    <script>
        const equipoId = <?php echo $equipo_id; ?>;
        const frecuenciaMantenimiento = <?php echo $equipo['frecuencia_mantenimiento_meses']; ?>;
    </script>
</body>
</html>