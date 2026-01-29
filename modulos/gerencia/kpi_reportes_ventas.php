<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/menu_lateral.php';
require_once '../../includes/header_universal.php';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!verificarAccesoCargo([5, 43, 11, 27, 26, 42, 49, 16]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoUsuariocodigo = obtenerCargoCodigoPrincipalUsuario($_SESSION['usuario_id']);

$cargoOperario = $usuario['CodNivelesCargos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI's Sucursales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            padding: 0;
            margin: 0;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .iframe-container {
            flex: 1;
            width: 100%;
            position: relative;
            overflow: hidden;
            padding: 0;
            margin: 0;
            height: calc(100vh - 120px);
        }
        
        .iframe-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Para pantallas pequeñas */
        @media (max-width: 768px) {
            .iframe-container {
                height: calc(100vh - 150px);
            }
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, $esAdmin, 'Reportes de Ventas'); ?>
            
            <?php if (isset($_SESSION['exito'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['exito'] ?>
                    <?php unset($_SESSION['exito']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="iframe-container">
                <iframe src="https://lookerstudio.google.com/embed/reporting/01645813-489d-42ea-8b91-b71b001af772/page/vEdYF" 
                    frameborder="0" 
                    style="border:0" 
                    allowfullscreen sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox">
                </iframe>
            </div>
        </div>
    </div>
</body>
</html>