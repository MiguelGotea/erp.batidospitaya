<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Tipo de Auditoría</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            text-align: center;
        }
        
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .contenedor-seleccion {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .contenedor-seleccion h2 {
            color: #51B8AC;
            margin-bottom: 30px;
        }
        
        .botones-auditoria {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .btn-auditoria {
            background-color: #51B8AC;
            color: white;
            text-decoration: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn-auditoria:hover {
            background-color: #0E544C;
        }
        
        .btn-volver {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #0E544C;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-volver:hover {
            background-color: #08332d;
        }
    </style>
</head>
<body>
    <div class="contenedor-seleccion">
        <h2>Seleccione el tipo de auditoría</h2>
        
        <div class="botones-auditoria">
            <a href="agregar.php" class="btn-auditoria">
                <i class="fas fa-broom"></i> Auditoría de Limpieza
            </a>
            
            <a href="agregarpersonal.php" class="btn-auditoria">
                <i class="fas fa-user-tie"></i> Auditoría de Personal
            </a>
            
            <a href="agregarservicio.php" class="btn-auditoria">
                <i class="fas fa-concierge-bell"></i> Auditoría de Servicio
            </a>
        </div>
        
        <a href="logout.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al listado
        </a>
    </div>
</body>
</html>
