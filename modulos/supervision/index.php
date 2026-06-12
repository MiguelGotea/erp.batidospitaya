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

        /* Estilos de los indicadores integrados del core */
        <?php include '../../core/components/indicators/horarios_confirmacion/horarios_confirmacion_styles.php'; ?>
        <?php include '../../core/components/indicators/auditorias_pendientes/auditorias_pendientes_styles.php'; ?>
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
            <h2 class="category-title">Recursos Humanos</h2>
            <div class="modules">
                <a href="programar_horarios_operaciones.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 class="module-title">Gestión de RRHH</h3>
                </a>

                <a href="ver_horarios_compactos.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="module-title">Control de Asistencia</h3>
                </a>

                <a href="gestion_categorias_colaboradores.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="module-title">Gestión de Categorías</h3>
                </a>
            </div>

            <!-- Accesos Directos - Grupo 2: Comunicación Interna -->
            <h2 class="category-title">Comunicación Interna</h2>
            <div class="modules">
                <a href="auditorias_original/index_avisos_publico.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="module-title">Vista Pública</h3>
                </a>

                <a href="auditorias_original/index.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3 class="module-title">Auditorías de Desempeño</h3>
                </a>
            </div>

            <!-- Accesos Directos - Grupo 3: Supervisión -->
            <h2 class="category-title">Supervisión</h2>
            <div class="modules">
                <a href="auditorias_original/auditinternas/auditorias_consolidadas.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <h3 class="module-title">Auditorías de Efectivo</h3>
                </a>
            </div>

            <!-- Accesos Directos - Grupo 4: Mantenimiento y Equipos -->
            <h2 class="category-title">Mantenimiento y Equipos</h2>
            <div class="modules">
                <a href="../supervision/pruebaodoo.php" class="module-card">
                    <div class="module-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="module-title">Solicitudes</h3>
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