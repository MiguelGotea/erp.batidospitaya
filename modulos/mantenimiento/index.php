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
            font-size: 1.5rem !important;
            margin: 30px 0 20px 0;
            padding-left: 15px;
            border-left: 5px solid #51B8AC;
            font-weight: 600;
        }

        /* Estilos específicos para el modal de detalles de pendientes */
        .modal-body {
            max-height: 400px;
            overflow-y: auto;
        }

        .item-pendiente {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .item-pendiente:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- ya existe en el css de menu lateral -->
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