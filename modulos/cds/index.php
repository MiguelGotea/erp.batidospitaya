<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo([19, 16]) && !$esAdmin) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadena De Suministros - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet"
        href="../../assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/indexmodulos.css') ?>">
    <!-- CSS propio con manejo de versiones  evitar cache de buscador -->
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(12px, 2vw, 18px) !important;
        }

        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
            color: #333;
        }
    </style>
</head>

<body>

    <body>
        <?php echo renderMenuLateral($cargoOperario); ?>
        <div class="main-container">
            <div class="contenedor-principal">
                <?php echo renderHeader($usuario, $esAdmin); ?>

                <h2 class="section-title">
                    <i class="fas fa-chart-line"></i> Indicadores de Control
                </h2>
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i> Accesos Rápidos
                </h2>
                <div class="quick-access-grid">
                    <a href="../rh/ver_marcaciones_todas.php" class="quick-access-card">
                        <div class="quick-access-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="quick-access-title">Marcaciones</div>
                    </a>

                    <a href="../mantenimiento/historial_solicitudes.php" class="quick-access-card">
                        <div class="quick-access-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="quick-access-title">Solicitudes de Mantenimiento</div>
                    </a>
                </div>
            </div>
        </div>
    </body>

</html>