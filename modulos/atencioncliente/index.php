<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!tienePermiso('index_atencioncliente', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Aquí se podría añadir funciones específicas para atención al cliente si necesitas
// mostrar datos como reclamos pendientes, cumpleaños del día, etc.
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atención al Cliente - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet"
        href="../../core/assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/core/assets/css/indexmodulos.css') ?>">
    <!-- CSS propio con manejo de versiones  evitar cache de buscador -->
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        /* Colores específicos para cada tipo */
        .reclamos-pendientes {
            border-left: 5px solid #dc3545;
        }

        .reclamos-pendientes .pendiente-count {
            color: #dc3545;
        }

        .reclamos-pendientes .pendiente-alert {
            color: #dc3545;
        }

        .cumpleanos-hoy {
            border-left: 5px solid #28a745;
        }

        .cumpleanos-hoy .pendiente-count {
            color: #28a745;
        }

        .cumpleanos-hoy .pendiente-alert {
            color: #28a745;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false); ?>

            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>

            <!-- Sección de pendientes (puede implementarse luego) -->
            <!--
            <div class="pendientes-container">
                <a href="../supervision/auditorias_original/nuevoreclamo.php" class="pendiente-card reclamos-pendientes">
                    <div class="module-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="pendiente-count">3</div>
                    <div class="pendiente-label">Reclamos pendientes</div>
                    <small class="pendiente-alert">Requieren atención</small>
                </a>
                
                <a href="cumpleanos_clientes.php" class="pendiente-card cumpleanos-hoy">
                    <div class="module-icon">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <div class="pendiente-count">5</div>
                    <div class="pendiente-label">Cumpleaños hoy</div>
                    <small class="pendiente-alert">Clientes a felicitar</small>
                </a>
            </div>
            -->
            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Accesos Rápidos
            </h2>

            <div class="quick-access-grid">
                <a href="cumpleanos_clientes.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="quick-access-title">Cumpleaños Clientes</div>
                </a>

                <a href="../supervision/auditorias_original/nuevoreclamo.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-access-title">Nuevo Reclamo</div>
                </a>
                <a href="../rh/ver_marcaciones_todas.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-access-title">Control de Asistencia</div>
                </a>
                <a href="resenas_google.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-access-title">Reseñas</div>
                </a>
            </div>
        </div>
    </div>
</body>

</html>