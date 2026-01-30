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
    <link rel="stylesheet"
        href="../../core/assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/core/assets/css/indexmodulos.css') ?>">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        .category-title {
            color: #0E544C;
            font-size: 1.5rem;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #51B8AC;
            text-align: center;
        }

        @media (max-width: 768px) {
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
    <div class="main-container">
        <div class="sub-container">
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