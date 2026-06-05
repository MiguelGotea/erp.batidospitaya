<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditorías, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16, 22, 28, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 22, 28, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

if (!isset($_SESSION['reclamo_exitoso'])) {
    header("Location: nuevoreclamo.php");
    exit();
}

$reclamoId = $_SESSION['reclamo_id'];
unset($_SESSION['reclamo_exitoso'], $_SESSION['reclamo_id']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reclamo Registrado</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <style>
        * {
            font-size: clamp(11px, 2vw, 16px) !important;
        }

        body {
            font-family: 'Calibri', sans-serif;
            background-color: #F6F6F6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .main-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-container {
            margin-bottom: 20px;
        }

        .logo {
            max-width: 120px;
        }

        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .success-icon {
            color: #06D6A0;
            font-size: 50px !important;
            margin-bottom: 20px;
        }

        h1 {
            color: #0E544C;
            margin-bottom: 20px;
        }

        .reclamo-id {
            font-size: 24px !important;
            font-weight: bold;
            color: #51B8AC;
            margin: 20px 0;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #51B8AC;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0E544C;
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <div class="logo-container">
            <a href="logout.php">
                <img src="/core/assets/img/Logo.svg" alt="Logo de la empresa" class="logo">
            </a>
        </div>
        <div class="container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Reclamo Registrado Exitosamente</h1>
            <p>El reclamo ha sido guardado con el siguiente código:</p>
            <div class="reclamo-id"><?php echo $reclamoId; ?></div>
            <a href="nuevoreclamo.php" class="btn">Nuevo Reclamo</a>
            <a href="index_reclamos_publico.php" class="btn-agregar" target="_blank">
                <i class="fas fa-users"></i> <span class="btn-text">Ver Reclamos</span>
            </a>
            <a href="../../index.php" class="btn" style="background-color: #6c757d; margin-left: 10px;">Regresar</a>
        </div>
    </div>
</body>

</html>