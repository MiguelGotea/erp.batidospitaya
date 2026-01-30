<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Verificar acceso al módulo RH (Código 13 para Jefe de RH)
//verificarAccesoModulo('diseno');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
// Verificar acceso al módulo
if (!tienePermiso('index_diseno', 'vista', $cargoOperario)) {
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
    <link rel="stylesheet"
        href="../../core/assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/core/assets/css/indexmodulos.css') ?>">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
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
            <?php echo renderHeader($usuario, false, ''); ?>

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