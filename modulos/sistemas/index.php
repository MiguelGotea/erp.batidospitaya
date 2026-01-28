<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/header_universal.php';
require_once '../../includes/menu_lateral.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo(15)) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema TI - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/indexmodulos.css') ?>"> <!-- CSS propio con manejo de versiones  evitar cache de buscador -->
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
            color: #333;
            margin: 0;
            padding: 0;
        }
        

    </style>
</head>
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
 
        </div>
    </div>
</body>
</html>