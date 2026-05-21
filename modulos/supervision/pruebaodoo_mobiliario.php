<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

//******************************Estándar para header******************************

$usuario = obtenerUsuarioActual();
// Verificar acceso al módulo Líderes (CodNivelesCargos 5) y Jefe de CDS (19)
verificarAccesoCargo([14, 16, 49]);

if (!verificarAccesoCargo([14, 16, 49])) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
$cargoUsuariocodigo = obtenerCargoCodigoPrincipalUsuario($_SESSION['usuario_id']);

// Obtener sucursales del usuario si es líder (código 5) o jefe de CDS (código 19)
$sucursalesUsuario = [];
$urlOdoo = "https://pitaya-mantenimiento.odoo.com/mobiliario"; // URL base

if ((verificarAccesoCargo([5, 49]) || verificarAccesoCargo([19, 49]))) {
    // Para líderes (código 5)
    if (verificarAccesoCargo([5, 49])) {
        $sucursalesUsuario = obtenerSucursalesLider($_SESSION['usuario_id']);
    }
    // Para jefe de CDS (código 19)
    elseif (verificarAccesoCargo([19, 49])) {
        // Obtener la sucursal CDS (código 6)
        global $conn;
        $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo = 6");
        $stmt->execute();
        $sucursalesUsuario = $stmt->fetchAll();
    }
    
    // Si tiene sucursales asignadas, usar la primera para el enlace
    if (!empty($sucursalesUsuario)) {
        $sucursalPrincipal = $sucursalesUsuario[0]['codigo'];
        $urlOdoo = "https://pitaya-mantenimiento.odoo.com/mobiliario-" . $sucursalPrincipal;
    }
}

//******************************Estándar para header, termina******************************
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petición de Equipos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #0E544C;
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
        
        .sucursal-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <?php if (verificarAccesoCargo([5, 16, 19, 49])): ?>
                        <a href="pruebaodoo.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo.php' ? 'activo' : '' ?>">
                            <i class="fas fa-sticky-note"></i> <span class="btn-text">Solicitudes Pendientes</span>
                        </a>
                    <?php endif; ?>
                    <?php if (verificarAccesoCargo([5, 16, 19, 49])): ?>
                        <a href="pruebaodoo_mantenimiento.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo_mantenimiento.php' ? 'activo' : '' ?>">
                            <i class="fas fa-tools"></i> <span class="btn-text">Mantenimiento</span>
                        </a>
                    <?php endif; ?>
                    <?php if (verificarAccesoCargo([5, 16, 19, 49])): ?>
                        <a href="pruebaodoo_mobiliario.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo_mobiliario.php' ? 'activo' : '' ?>">
                            <i class="fas fa-desktop"></i> <span class="btn-text">Equipos</span>
                        </a>
                    <?php endif; ?>
                    <?php if (verificarAccesoCargo([5, 16, 19, 49])): ?>
                        <a href="pruebaodoo.php?finalizadas=1" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'pruebaodoo.php' ? 'activo' : '' ?>">
                            <i class="fas fa-check-circle"></i> <span class="btn-text">Solicitudes Finalizadas</span>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= false ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= false ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
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
        
        <?php if (!empty($sucursalesUsuario) && (verificarAccesoCargo([5, 49]) || verificarAccesoCargo([19, 49]))): ?>
            <div style="text-align:center;" class="sucursal-info">
                Sucursal: <?= htmlspecialchars($sucursalesUsuario[0]['nombre']) ?> <p style="display:none;">(Código: <?= $sucursalesUsuario[0]['codigo'] ?>)</p>
            </div>
        <?php endif; ?>
        
        <iframe src="<?= $urlOdoo ?>"
                width="100%" 
                height="600" 
                style="border:none;">
        </iframe>
        
        <!-- <iframe src="https://pitaya-mantenimiento.odoo.com/my/tickets" 
                width="100%" 
                height="600" 
                style="border:none;">
        </iframe> -->

    </div>
</body>
</html>