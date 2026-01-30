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
if (!tienePermiso('index_experienciadigital', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Experiencia Digital - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet"
        href="../../core/assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/core/assets/css/indexmodulos.css') ?>">
    <!-- CSS propio con manejo de versiones  evitar cache de buscador -->
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
        .indicator-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .indicator-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #51B8AC 0%, #0E544C 100%);
        }

        .indicator-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .indicator-count {
            font-size: 2.5rem !important;
            font-weight: bold;
            color: #0E544C;
            margin: 10px 0;
        }

        .indicator-fecha {
            font-size: 0.8rem !important;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .indicator-titulo {
            color: #666;
            font-size: 0.95rem !important;
            font-weight: 500;
        }

        .indicator-info {
            text-align: center;
            margin-top: 5px;
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

            <!-- Contenedor para indicadores -->
            <!-- Sección: Indicadores de Control -->
            <h2 class="section-title" style="Display:none">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>

            <div class="indicadores-container" style="Display:none">
                <div class="indicator-card" onclick="">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="indicator-count">
                        <?= $stats['total'] - $stats['agendado'] - $stats['finalizado'] ?>
                    </div>
                    <div class="indicator-titulo">
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

                <div class="indicator-card" onclick="" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="indicator-count">
                        <?= $stats['finalizado'] ?>
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-fecha">
                            Solicitudes Concluidas
                        </div>
                        <div class="indicator-titulo">
                            --
                        </div>
                    </div>
                </div>

                <div class="indicator-card" onclick="" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="indicator-count">
                        <?= $stats['total'] ?>
                    </div>
                    <div class="indicator-info">
                        <div class="indicator-fecha">
                            Solicitudes Totales
                        </div>
                        <div class="indicator-titulo">
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
                <a href="../supervision/auditorias_original/nuevoreclamo.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clipboard"></i>
                    </div>
                    <div class="quick-access-title">Nuevo Reclamo</div>
                </a>

                <a href="../atencioncliente/resenas_google.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="quick-access-title">Reseñas Google</div>
                </a>

                <a href="../atencioncliente/cumpleanos_clientes.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fa fa-calendar"></i>
                    </div>
                    <div class="quick-access-title">Cumpleaños Club Pitaya</div>
                </a>
            </div>
        </div>
    </div>
</body>

</html>