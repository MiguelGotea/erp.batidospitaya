<?php
require_once '../mantenimiento/models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
//verificarAccesoModulo('sistema'); Esto ya no se usa

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
// Verificar acceso al módulo
if (!tienePermiso('index_infraestructura', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infraestructura - Batidos Pitaya</title>
    <link rel="stylesheet"
        href="../../core/assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/core/assets/css/indexmodulos.css') ?>">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .indicator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .indicator-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem !important;
            background: linear-gradient(135deg, #51B8AC20 0%, #0E544C20 100%);
            color: #0E544C;
        }


        /* Sección de título */
        .section-title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin: 30px 0 20px 0;
            padding-left: 15px;
            border-left: 5px solid #51B8AC;
            font-weight: 600;
        }

        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(135px, 135px));
            /*Espacio entre las cartas del módulo*/
            gap: 20px;
            margin-bottom: 30px;
        }

        .indicator-status {
            font-size: 0.85rem !important;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        .indicator-action {
            color: #51B8AC;
            font-size: 0.85rem !important;
            font-weight: 600;
        }

        .indicator-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .status-verde {
            background: #d4edda;
            color: #155724;
        }

        .status-amarillo {
            background: #fff3cd;
            color: #856404;
        }

        .status-rojo {
            background: #f8d7da;
            color: #721c24;
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

            .dashboard-grid {
                grid-template-columns: 1fr;
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

            .pendientes-info {
                text-align: center;
            }

            .pendientes-fecha {
                font-size: 0.7rem !important;
            }

            .indicadores-container {
                grid-template-columns: 1fr;
            }

            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        /* Estilos para el contenedor de indicadores */
        .indicadores-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Cards de indicadores mejoradas */
        .pendientes-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .pendientes-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #51B8AC 0%, #0E544C 100%);
        }

        .pendientes-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
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

        .pendientes-count {
            font-size: 2.5rem !important;
            font-weight: bold;
            color: #0E544C;
            margin: 10px 0;
        }

        .pendientes-fecha {
            font-size: 0.8rem !important;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .pendientes-titulo {
            color: #666;
            font-size: 0.95rem !important;
            font-weight: 500;
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
    </style>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="contenedor-principal">
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

            <!-- Contenedor para indicadores -->
            <!-- Sección: Indicadores de Control -->
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>

            <div class="indicadores-container">


                <div class="pendientes-container" onclick="">

                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="pendientes-count">
                        <?= $stats['total'] - $stats['agendado'] - $stats['finalizado'] ?>
                    </div>
                    <div class="pendientes-titulo">
                        Solicitudes Pendientes por Agendar
                    </div>
                    <div class="indicator-meta">
                        <span class="indicator-status <?= 'status-rojo' ?>">
                            <?= 1 > 0 ? 'Revisar' : 'Al día' ?>
                        </span>
                        <span class="indicator-action">
                            <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>

                <div class="pendientes-container" onclick="" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
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



                <div class="pendientes-container" onclick="" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
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

            <!-- Sección: Accesos Rápidos -->
            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Accesos Rápidos
            </h2>


            <div class="quick-access-grid">
                <a href="../mantenimiento/formulario_mantenimiento.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="quick-access-title">Solicitud Mantenimiento</div>
                </a>

                <a href="../mantenimiento/historial_solicitudes.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <div class="quick-access-title">Solicitudes</div>
                </a>

                <a href="../mantenimiento/programacion_solicitudes.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fa fa-calendar"></i>
                    </div>
                    <div class="quick-access-title">Calendario</div>
                </a>


            </div>
        </div>
    </div>
</body>

</html>