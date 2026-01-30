<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/header_universal.php';
require_once '../../includes/menu_lateral.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo([17])) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Almacén - Batidos Pitaya</title>
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
        }
        
        
                
                /* Estilos para la tarjeta de cumpleaños */
        .cumpleanos-container {
            max-width: 1200px;
            margin: 0 auto 30px auto;
        }
        
        .cumpleanos-card {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 50%, #FF6B6B 100%);
            border-radius: 20px;
            padding: 30px;
            color: white;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
            position: relative;
            overflow: hidden;
            border: 3px solid rgba(255, 255, 255, 0.2);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .cumpleanos-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .cumpleanos-content {
            display: flex;
            align-items: center;
            gap: 25px;
            position: relative;
            z-index: 2;
        }
        
        .cumpleanos-icon {
            font-size: 4rem !important;
            animation: bounce 2s infinite;
            min-width: 80px;
            text-align: center;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .cumpleanos-text {
            flex: 1;
        }
        
        .cumpleanos-title {
            font-size: 2rem !important;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .cumpleanos-message {
            font-size: 1.1rem !important;
            margin-bottom: 15px;
            line-height: 1.6;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .cumpleanos-details {
            font-size: 1rem !important;
            opacity: 0.9;
            line-height: 1.5;
        }
        
        .cumpleanos-confetti {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 3rem !important;
            animation: spin 4s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Efectos de confeti adicionales */
        .cumpleanos-card::after {
            content: '🎉🎊🥳';
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-size: 1.5rem;
            opacity: 0.7;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cumpleanos-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .cumpleanos-title {
                font-size: 1.5rem !important;
            }
            
            .cumpleanos-message {
                font-size: 1rem !important;
            }
            
            .cumpleanos-icon {
                font-size: 3rem !important;
            }
            
            .cumpleanos-confetti {
                position: relative;
                top: auto;
                right: auto;
                margin-top: 10px;
            }
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
           <div class="quick-access-grid">
                <a href="../rh/ver_marcaciones_todas.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="quick-access-title">Marcaciones</div>
                </a>
            </div>        
        </div>
    </div>
</body>
</html>