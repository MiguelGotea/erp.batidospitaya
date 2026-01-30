<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

// Verificar acceso al módulo RH (Código 13 para Jefe de RH)
//verificarAccesoModulo('diseno');

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo([25])) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diseño - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
        }

        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 135px));
            /*Espacio entre las cartas del módulo*/
            gap: 20px;
            margin-bottom: 30px;
        }

        .module-card {
            background: white;
            border-radius: 8px;
            padding: 7px;
            /*Espacio de las cartas del módulo*/
            width: auto;
            max-width: 135px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            /*Texto centrado*/
            display: flex;
            flex-direction: column;
            align-items: center !important;
            /* Centrado horizontal */
            justify-content: center !important;
            /* Centrado vertical */
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

        .module-desc {
            color: #666;
            font-size: 0.9rem;
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .module-title-page {
            color: #51B8AC;
            font-size: 1.8rem;
        }

        .category-title {
            color: #0E544C;
            font-size: 1.5rem;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #51B8AC;
            text-align: center;
            /*Texto de categorías al centro*/
        }

        @media (max-width: 768px) {
            .modules {
                grid-template-columns: repeat(3, 1fr);
                /* 3 columnas en móvil */
                gap: 10px;
                /* Reducir espacio entre tarjetas */
            }

            .module-card {
                padding: 10px 5px;
                /* Ajustar espaciado interno */
                max-width: 100%;
                /* Ocupar todo el ancho disponible */
                height: 100%;
                /* Asegurar altura consistente */
            }

            .module-icon {
                font-size: 1.8rem !important;
                /* Reducir tamaño de icono */
                margin-bottom: 5px;
                /* Menos espacio entre icono y texto */
            }

            .module-title {
                font-size: 0.9rem !important;
                /* Reducir tamaño de texto */
                margin-bottom: 5px;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, $esAdmin, ''); ?>

            <div class="module-header">
                <h1 class="module-title-page">Área de Diseño Gráfico y Multimedia</h1>
            </div>

            <div class="modules">
                <a href="auditorias_original/index_auditorias_publico.php" class="module-card">

                </a>
            </div>
        </div>
    </div>
</body>

</html>