<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();

$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!tienePermiso('index_produccion', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producción - Batidos Pitaya</title>
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 135px));
            gap: 20px;
            margin-bottom: 30px;
        }

        .module-card {
            background: white;
            border-radius: 8px;
            padding: 7px;
            width: auto;
            max-width: 135px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .module-icon {
            font-size: 2.5rem;
            color: #51B8AC;
            margin-bottom: 12px;
        }

        .module-title {
            margin: 0;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #0E544C;
        }

        .info-container {
            max-width: 1200px;
            margin: 0 auto 30px auto;
        }

        .info-card {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            border-radius: 12px;
            padding: 25px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-2px);
        }

        .info-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 1.4rem !important;
        }

        .info-title i {
            font-size: 1.6rem;
        }

        .info-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-icon {
            font-size: 4rem !important;
            opacity: 0.8;
            min-width: 100px;
            text-align: center;
        }

        .info-text {
            flex: 1;
            text-align: left;
            padding-left: 20px;
        }

        .info-message {
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .info-details {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .btn-action {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-action:hover {
            background: white;
            color: #333;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .modules {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .module-card {
                padding: 10px 5px;
                max-width: 100%;
                height: 100%;
            }

            .module-icon {
                font-size: 1.8rem !important;
                margin-bottom: 5px;
            }

            .module-title {
                font-size: 0.9rem !important;
                margin-bottom: 5px;
            }

            .info-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .info-text {
                text-align: center;
                padding-left: 0;
            }

            .info-icon {
                font-size: 3rem !important;
            }
        }

        /* Estilos para el modal de información */
        .modal-info {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content-info {
            background-color: white;
            margin: 5% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header-info {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header-info h3 {
            margin: 0;
            font-size: 1.4rem !important;
        }

        .close-modal {
            font-size: 2rem;
            cursor: pointer;
            transition: color 0.3s ease;
            line-height: 1;
        }

        .close-modal:hover {
            color: #ffeb3b;
        }

        .modal-body-info {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        /* Responsive para el modal */
        @media (max-width: 768px) {
            .modal-content-info {
                margin: 10% auto;
                width: 95%;
            }

            .modal-body-info {
                padding: 15px;
            }
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
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.02);
            }

            100% {
                transform: scale(1);
            }
        }

        .cumpleanos-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }

            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
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

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .cumpleanos-text {
            flex: 1;
        }

        .cumpleanos-title {
            font-size: 2rem !important;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .cumpleanos-message {
            font-size: 1.1rem !important;
            margin-bottom: 15px;
            line-height: 1.6;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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
            <?php echo renderHeader($usuario, false, ''); ?>

            <div class="module-header" style="display:none;">
                <h1 class="module-title-page">Área de Producción</h1>
            </div>

            <!-- Grupo: Comunicación Interna -->
            <h2 class="category-title">Comunicación Interna</h2>
            <div class="modules">
                <a href="../supervision/auditorias_original/index_avisos_publico.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="module-title">Avisos</h3>
                </a>
            </div>

            <h2 class="category-title">Mantenimiento y Equipos</h2>
            <div class="modules">
                <!-- Histórico -->
                <a href="../mantenimiento/historial_solicitudes.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="module-title">Solicitudes</h3>
                </a>
            </div>

            <h2 class="category-title">Recursos Humanos</h2>
            <div class="modules">
                <a href="../rh/ver_marcaciones_todas.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="module-title">Ver Marcaciones</h3>
                </a>
            </div>
        </div>
    </div>
</body>

</html>