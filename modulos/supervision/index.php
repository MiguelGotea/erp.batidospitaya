<?php

/**
 * Módulo de Supervisión - Index Principal (Core Rediseñado)
 * 
 * Este index ha sido rediseñado para utilizar el estilo limpio y unificado del ERP.
 * Integra los componentes e indicadores core:
 * - Horarios por Confirmar (horarios_confirmacion)
 * - Auditorías Pendientes (auditorias_pendientes)
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (gestionado dinámicamente desde el panel de permisos)
verificarPermisoORedireccionar('index_supervision', 'vista', $cargoOperario, '../index.php');

// 1. Cargar e Inicializar Indicador: Horarios por Confirmar
require_once '../../core/components/indicators/horarios_confirmacion/horarios_confirmacion_functions.php';
$hc_estadoConfirmacion = hc_obtenerEstadoHorariosPendientesConfirmacion($usuario['CodOperario'] ?? $_SESSION['usuario_id'] ?? null);

// 2. Cargar e Inicializar Indicador: Auditorías Pendientes
require_once '../../core/components/indicators/auditorias_pendientes/auditorias_pendientes_functions.php';
$audpend_estadoAuditoriasMensual = audpend_obtenerEstadoAuditoriasMensual();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisión - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/core/assets/css/indexmodulos.css') ?>">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
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
            margin: 0;
            padding: 0;
        }

        /* Estilos para modales estándar del ERP */
        .modal-pendientes {
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

        .modal-content-pendientes {
            background-color: white;
            margin: 5% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
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

        .modal-header-pendientes {
            background: #0E544C;
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header-pendientes h3 {
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

        .modal-body-pendientes {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        /* Estilos de los indicadores integrados del core */
        <?php include '../../core/components/indicators/horarios_confirmacion/horarios_confirmacion_styles.php'; ?><?php include '../../core/components/indicators/auditorias_pendientes/auditorias_pendientes_styles.php'; ?>
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, ''); ?>

            <div class="module-header">
                <h1 class="module-title-page">Área de Supervisión</h1>
            </div>

            <!-- Contenedor para indicadores de Control -->
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>
            <div class="indicadores-container">
                <!-- Indicador 1: Horarios por Confirmar (Core) -->
                <?php include '../../core/components/indicators/horarios_confirmacion/horarios_confirmacion_card.php'; ?>

                <!-- Indicador 2: Auditorías Pendientes (Core) -->
                <?php include '../../core/components/indicators/auditorias_pendientes/auditorias_pendientes_card.php'; ?>
            </div>

            <!-- Accesos Directos - Grupo 1: Recursos Humanos -->
            <h2 class="section-title">
                <i class="fas fa-users"></i> Recursos Humanos
            </h2>
            <div class="quick-access-grid">
                <a href="programar_horarios_operaciones.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="quick-access-title">Gestión de RRHH</div>
                </a>

                <a href="ver_horarios_compactos.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-access-title">Control de Asistencia</div>
                </a>
            </div>

            <!-- Accesos Directos - Grupo 2: Comunicación Interna -->
            <h2 class="section-title">
                <i class="fas fa-bullhorn"></i> Comunicación Interna
            </h2>
            <div class="quick-access-grid">
                <a href="auditorias_original/index_avisos_publico.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="quick-access-title">Vista Pública</div>
                </a>

                <a href="auditorias_original/index.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="quick-access-title">Auditorías de Desempeño</div>
                </a>
            </div>

            <!-- Accesos Directos - Grupo 3: Supervisión -->
            <h2 class="section-title">
                <i class="fas fa-clipboard-check"></i> Supervisión
            </h2>
            <div class="quick-access-grid">
                <a href="auditorias_original/auditinternas/auditorias_consolidadas.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="quick-access-title">Auditorías de Efectivo</div>
                </a>
            </div>

            <!-- Accesos Directos - Grupo 4: Mantenimiento y Equipos -->
            <h2 class="section-title">
                <i class="fas fa-wrench"></i> Mantenimiento y Equipos
            </h2>
            <div class="quick-access-grid">
                <a href="../supervision/pruebaodoo.php" class="quick-access-card">
                    <div class="quick-access-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="quick-access-title">Solicitudes</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Modales del Core -->
    <?php include '../../core/components/indicators/horarios_confirmacion/horarios_confirmacion_modal.php'; ?>
    <?php include '../../core/components/indicators/auditorias_pendientes/auditorias_pendientes_modal.php'; ?>

    <!-- Scripts del Core -->
    <script>
        <?php include '../../core/components/indicators/horarios_confirmacion/horarios_confirmacion_scripts.php'; ?>
        <?php include '../../core/components/indicators/auditorias_pendientes/auditorias_pendientes_scripts.php'; ?>
    </script>
</body>

</html>