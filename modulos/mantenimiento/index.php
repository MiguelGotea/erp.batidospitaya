<?php
require_once 'models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';


$usuario = obtenerUsuarioActual();
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];
$CodigoCargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!tienePermiso('index_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}



?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="../../assets/css/indexmodulos.css?v=<?php echo mt_rand(1, 10000); ?>">
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

            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .modal-content-pendientes {
                margin: 10% auto;
                width: 95%;
            }

            .item-tardanza {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .btn-justificar {
                margin-left: 0;
                width: 100%;
                text-align: center;
            }

            .pendientes-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .pendientes-info {
                text-align: center;
            }

            .pendientes-fecha {
                font-size: 0.7rem !important;
            }

            .indicadores-container {
                flex-direction: column;
                align-items: center;
            }

            .pendientes-container {
                min-width: 100%;
                max-width: 100%;
            }
        }

        /* Estilos para el contenedor de indicadores */
        .indicadores-container {
            display: flex;
            flex-direction: row;
            /* En una sola fila */
            gap: 15px;
            margin-bottom: 30px;
            max-width: 1200px;
            margin: 0 auto 30px auto;
            flex-wrap: wrap;
            justify-content: center;
        }

        .pendientes-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            flex: 1;
        }

        .pendiente-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s, box-shadow 0.3s;
            min-width: 200px;
            max-width: 250px;
        }

        .pendiente-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .pendientes-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 5px;
        }

        .pendientes-count {
            font-size: 2.5rem !important;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            min-width: 80px;
        }

        .pendientes-fecha {
            font-size: 0.8rem !important;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .pendientes-titulo {
            font-size: 0.9rem !important;
            font-weight: 600;
            margin-top: 5px;
        }

        .pendientes-info {
            text-align: center;
            margin-top: 5px;
        }

        .pendientes-detalle {
            margin-bottom: 10px;
            font-size: 0.6rem;
            opacity: 0.9;
        }

        .pendientes-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="contenedor-principal"> <!-- ya existe en el css de menu lateral -->
            <!-- todo el contenido existente -->

            <!-- Renderizar header universal -->
            <?php echo renderHeader($usuario, false, ''); ?>

            <?php
            $ticket = new Ticket();
            $tickets = $ticket->getAll();
            // Obtener estadísticas
            $stats = [
                'total' => count($tickets),
                'solicitado' => count(array_filter($tickets, fn($t) => $t['status'] === 'solicitado')),
                'agendado' => count(array_filter($tickets, fn($t) => $t['status'] === 'agendado')),
                'finalizado' => count(array_filter($tickets, fn($t) => $t['status'] === 'finalizado'))
            ];
            ?>

            <h2 class="category-title">Indicadores</h2>
            <!-- Contenedor para indicadores -->
            <div class="indicadores-container">
                <div class="pendientes-container" style="margin-bottom: 30px;">
                    <div class="pendientes-card" onclick="" style="cursor: pointer;">
                        <div class="pendientes-content">
                            <div class="pendientes-count">
                                <?= $stats['total'] - $stats['agendado'] - $stats['finalizado'] ?>
                            </div>
                            <div class="pendientes-info">
                                <div class="pendientes-fecha">
                                    Solicitudes Pendientes por Agendar
                                </div>
                                <div class="pendientes-titulo">
                                    --
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pendientes-container" style="margin-bottom: 30px;">
                    <div class="pendientes-card" onclick="" style="cursor: pointer;">
                        <div class="pendientes-content">
                            <div class="pendientes-count">
                                <?= $stats['finalizado'] ?>
                            </div>
                            <div class="pendientes-info">
                                <div class="pendientes-fecha">
                                    Solicitudes Concluidas
                                </div>
                                <div class="pendientes-titulo">
                                    --
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pendientes-container" style="margin-bottom: 30px;">
                    <div class="pendientes-card" onclick="" style="cursor: pointer;">
                        <div class="pendientes-content">
                            <div class="pendientes-count">
                                <?= $stats['total'] ?>
                            </div>
                            <div class="pendientes-info">
                                <div class="pendientes-fecha">
                                    Solicitudes Totales
                                </div>
                                <div class="pendientes-titulo">
                                    --
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
</body>

</html>