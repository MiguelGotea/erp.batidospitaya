<?php
// public_html/modulos/mantenimiento/equipos_lista.php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once 'config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
$codigo_sucursal_busqueda = $sucursales[0]['nombre'];

// Verificar permisos (5, 43 para líderes de sucursal, 35 para líder de infraestructura)
if (!in_array($cargoOperario, [5, 43, 35])) {
    die("Acceso denegado");
}

$esLiderInfraestructura = ($cargoOperario == 35);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Equipos - Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Gestión de Equipos</h1>
                <div>
                    <?php if ($esLiderInfraestructura): ?>
                        <a href="equipos_registro.php" class="btn btn-primario">+ Nuevo Equipo</a>
                        <a href="equipos_calendario.php" class="btn btn-secundario">📅 Calendario</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tabla-contenedor">
                <table class="tabla-equipos" id="tablaEquipos">
                    <thead>
                        <tr>
                            <th>
                                <div class="filtro-columna">
                                    Código
                                    <span class="filtro-icono" onclick="toggleFiltro('codigo')">▼</span>
                                    <div class="filtro-dropdown" id="filtro-codigo"></div>
                                </div>
                            </th>
                            <th>
                                <div class="filtro-columna">
                                    Nombre
                                    <span class="filtro-icono" onclick="toggleFiltro('nombre')">▼</span>
                                    <div class="filtro-dropdown" id="filtro-nombre"></div>
                                </div>
                            </th>
                            <th>
                                <div class="filtro-columna">
                                    Tipo
                                    <span class="filtro-icono" onclick="toggleFiltro('tipo')">▼</span>
                                    <div class="filtro-dropdown" id="filtro-tipo"></div>
                                </div>
                            </th>
                            <th>
                                <div class="filtro-columna">
                                    Ubicación Actual
                                    <span class="filtro-icono" onclick="toggleFiltro('ubicacion')">▼</span>
                                    <div class="filtro-dropdown" id="filtro-ubicacion"></div>
                                </div>
                            </th>
                            <th>
                                <div class="filtro-columna">
                                    Último Mantenimiento
                                    <span class="filtro-icono" onclick="toggleFiltro('ultimo_mtto')">▼</span>
                                    <div class="filtro-dropdown" id="filtro-ultimo_mtto"></div>
                                </div>
                            </th>
                            <th>
                                <div class="filtro-columna">
                                    Próximo Mtto Preventivo
                                    <span class="filtro-icono" onclick="toggleFiltro('proximo_mtto')">▼</span>
                                    <div class="filtro-dropdown" id="filtro-proximo_mtto"></div>
                                </div>
                            </th>
                            <th>
                                <div class="filtro-columna">
                                    Estado
                                    <span class="filtro-icono" onclick="toggleFiltro('estado')">▼</span>
                                    <div class="filtro-dropdown" id="filtro-estado"></div>
                                </div>
                            </th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTabla">
                        <tr>
                            <td colspan="8" class="texto-centrado">
                                <div class="loading"></div> Cargando equipos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/equipos_lista.js"></script>
    <script>
        const esLiderInfraestructura = <?php echo $esLiderInfraestructura ? 'true' : 'false'; ?>;
        const usuarioId = <?php echo $_SESSION['usuario_id']; ?>;
        const cargoOperario = <?php echo $cargoOperario; ?>;
        const sucursalUsuario = '<?php echo $codigo_sucursal_busqueda; ?>';
    </script>
</body>
</html>