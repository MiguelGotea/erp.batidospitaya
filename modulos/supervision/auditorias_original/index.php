<?php
// index.php - Registros de Auditoría con filtros avanzados
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 21, 49, 52]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoOperario = $usuario['CodNivelesCargos'];
//******************************Estándar para header, termina******************************
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Auditoría</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">

    <!-- Librerías Estándar ERP -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- Estilos Estándar ERP -->
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">

    <!-- CSS personalizado de la página -->
    <link rel="stylesheet" href="css/auditorias.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Registros de Auditoría'); ?>

            <div class="container-fluid p-3">
                <!-- La navegación se movió al FAB y el botón Historial se eliminó por redundancia -->

                <!-- Tabla de auditorías -->
                <div class="table-responsive">
                    <table class="auditorias-table table table-hover" id="tablaAuditorias">
                        <thead>
                            <tr>
                                <th class="columna-numero">No.</th>
                                <th data-column="fecha_hora" data-type="daterange">
                                    Fecha
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="sucursal" data-type="list">
                                    Sucursal
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="persona" data-type="text">
                                    Colaborador
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="tipo_auditoria" data-type="list">
                                    Tipo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th class="columna-promedio">Puntaje</th>
                            </tr>
                        </thead>
                        <tbody id="tablaAuditoriasBody">
                            <!-- Datos cargados vía AJAX -->
                            <tr>
                                <td colspan="6" class="sin-registros">Cargando datos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        <label>Mostrar:</label>
                        <select id="registrosPorPagina" onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón Flotante con opciones (FAB) -->
    <?php if (verificarAccesoCargo([16, 21, 49])): ?>
        <div class="fab-container">
            <div class="fab-options">
                <a href="auditinternas/auditoria_promociones.php" class="fab-option">
                    <span class="fab-label">Promociones</span>
                    <div class="fab-icon-holder"><i class="fas fa-tags"></i></div>
                </a>
                <a href="auditinternas/auditoria_proceso.php" class="fab-option">
                    <span class="fab-label">Procesos</span>
                    <div class="fab-icon-holder"><i class="fas fa-clipboard-check"></i></div>
                </a>
                <a href="agregarservicio.php" class="fab-option">
                    <span class="fab-label">Auditoría Servicio</span>
                    <div class="fab-icon-holder"><i class="fas fa-concierge-bell"></i></div>
                </a>
                <a href="agregarpersonal.php" class="fab-option">
                    <span class="fab-label">Auditoría Personal</span>
                    <div class="fab-icon-holder"><i class="fas fa-user-tie"></i></div>
                </a>
                <a href="agregar.php" class="fab-option">
                    <span class="fab-label">Auditoría Limpieza</span>
                    <div class="fab-icon-holder"><i class="fas fa-broom"></i></div>
                </a>
            </div>
            <div class="btn-floating-pitaya" title="Nueva Auditoría">
                <i class="fas fa-plus"></i>
            </div>
        </div>
    <?php endif; ?>

    <!-- jQuery y Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript personalizado -->
    <script src="js/auditorias.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <script>
        // Inicialización de interacciones FAB si no están en auditorias.js
        $(document).ready(function() {
            $('.btn-floating-pitaya').on('click', function() {
                $('.fab-container').toggleClass('active');
            });

            // Cerrar FAB al hacer clic fuera
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.fab-container').length) {
                    $('.fab-container').removeClass('active');
                }
            });
        });
    </script>
</body>

</html>