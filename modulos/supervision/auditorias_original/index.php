<?php
// index.php - Registros de Auditoría con filtros avanzados
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditorías, ahora llama al del core;
require_once '../../../core/layout/menu_lateral.php';
require_once '../../../core/layout/header_universal.php';

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
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
    <!-- Font Awesome para iconos generales -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Bootstrap Icons para iconos de filtro -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- CSS personalizado -->
    <link rel="stylesheet" href="css/auditorias.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Registros de Auditoría'); ?>
            
            <div class="container-fluid p-3">
                
                <div class="buttons-container-nav mb-4" style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                    <a href="index.php" class="btn-agregar activo">
                        <i class="fas fa-clipboard-check"></i> <span class="btn-text">Historial</span>
                    </a>
                    
                    <?php if (verificarAccesoCargo([16])): ?>
                        <a href="agregar.php" class="btn-agregar"><i class="fas fa-cash-register"></i> Auditoría Limpieza</a>
                        <a href="agregarpersonal.php" class="btn-agregar"><i class="fas fa-wallet"></i> Auditoría Personal</a>
                        <a href="agregarservicio.php" class="btn-agregar"><i class="fas fa-boxes"></i> Auditoría Servicio</a>
                    <?php endif; ?>
                </div>
        
        <?php if (verificarAccesoCargo([21])): ?>
            <!-- Botones para agregar nuevo registro -->
            <div class="nueva-auditoria-container">
                <p>Nueva Auditoría</p>
                <a href="agregar.php" class="btn-agregar"><i class="fas fa-broom"></i> LIMPIEZA</a>
                <a href="agregarpersonal.php" class="btn-agregar"><i class="fas fa-user-tie"></i> PERSONAL</a>
                <a href="agregarservicio.php" class="btn-agregar"><i class="fas fa-concierge-bell"></i> SERVICIO</a>
                <a href="auditinternas/auditoria_proceso.php" class="btn-agregar"><i class="fas fa-clipboard-check"></i> PROCESOS</a>
                <a href="auditinternas/auditoria_promociones.php" class="btn-agregar"><i class="fas fa-tags"></i> PROMOCIONES</a>
            </div>
        <?php endif; ?>

        <!-- Tabla de auditorías -->
        <table class="auditorias-table" id="tablaAuditorias">
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- JavaScript personalizado -->
    <script src="js/auditorias.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>
