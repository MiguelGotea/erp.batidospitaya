<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once 'funciones_lideres.php';

// Verificar conexión a la base de datos
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Verificar sesión y obtener usuario
$usuario = obtenerUsuarioActual();
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];
$sucursalOperario = obtenerSucursalesLider($usuario['CodOperario']);

// Verificar acceso al módulo de líderes (Código 5 para Líder de Sucursal)
if (!verificarAccesoCargo([5, 43, 16])) {
    header('Location: ../index.php');
    exit();
}

// Obtener sucursales del líder actual
$sucursales = obtenerSucursalesLider($_SESSION['usuario_id']);

// Obtener tardanzas pendientes de reportar
$tardanzasPendientes = obtenerTardanzasPendientesLider($_SESSION['usuario_id']);
// Obtener faltas pendientes de reportar
$faltasPendientes = obtenerFaltasPendientesLider($_SESSION['usuario_id']);
// Obtener estado del horario pendiente
$estadoHorario = obtenerEstadoHorarioPendiente($_SESSION['usuario_id']);

// Verificar si el usuario está de cumpleaños
$cumpleanosInfo = verificarCumpleanosUsuario($_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Líder de Sucursal - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/lideres.css?v=<?php echo mt_rand(1, 10000); ?>">

</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="contenedor-principal">
            <!-- Renderizar header universal -->
            <?php
            $cantidadAnunciosNoLeidos = obtenerCantidadAnunciosNoLeidos($_SESSION['usuario_id']);
            echo renderHeader($usuario, '');
            ?>

            <!-- Sección: Indicadores de Control -->
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i> Indicadores de Control
            </h2>

            <!-- Tarjeta de Feliz Cumpleaños -->
            <?php if ($cumpleanosInfo): ?>
                <div class="cumpleanos-container" style="max-width: 1200px; margin: 0 auto 30px auto;">
                    <div class="cumpleanos-card">
                        <div class="cumpleanos-content">
                            <div class="cumpleanos-icon">
                                <i class="fas fa-birthday-cake"></i>
                            </div>
                            <div class="cumpleanos-text">
                                <h2 class="cumpleanos-title">
                                    ¡Feliz Cumpleaños <?= htmlspecialchars($cumpleanosInfo['nombre']) ?>! 🎉🎂
                                </h2>
                                <p class="cumpleanos-message">
                                    Hoy celebramos tu día especial 🥳 y queremos agradecerte por ser parte de nuestra
                                    familia
                                    en Batidos Pitaya 🍓.
                                    <?//= $cumpleanosInfo['edad'] ? "¡Felicidades por tus {$cumpleanosInfo['edad']} años! " : "" ?>
                                    Que este nuevo ciclo de vida esté lleno de éxitos, alegrías y momentos inolvidables.
                                </p>
                                <p class="cumpleanos-details">
                                    Te invitamos a pasar por tu batido de cortesía en cualquier sucursal si tienes membresía
                                    de Club Pitaya 🥤<br>
                                    <strong>Con nuestros mejores deseos,<br>El equipo de Batidos Pitaya 💜✨</strong>
                                </p>
                            </div>
                            <div class="cumpleanos-confetti">🎊</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="indicadores-container">

                <!-- Indicador de Tardanzas Pendientes -->
                <div class="indicator-container" onclick="mostrarModalTardanzas()" style="cursor: pointer;">

                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>

                    <div class="indicator-count">
                        <?= $tardanzasPendientes['total'] ?>
                    </div>

                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Tardanzas Pendientes
                        </div>

                        <div class="indicator-meta">
                            <span class="indicator-status tardanzas-indicador <?= $tardanzasPendientes['color'] ?>">
                                <?php
                                $diasRestantes = $tardanzasPendientes['dias_restantes'];
                                if ($tardanzasPendientes['total'] == 0) {
                                    echo 'Al día';
                                } elseif ($diasRestantes < 0) {
                                    echo 'Vencido hace ' . abs($diasRestantes) . ' días';
                                } elseif ($diasRestantes === 0) {
                                    echo 'Vence hoy';
                                } else {
                                    echo $diasRestantes . ' días restantes';
                                }
                                ?>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>

                </div>


                <!-- Indicador de Faltas Pendientes -->

                <div class="indicator-container" onclick="mostrarModalFaltas()" style="cursor: pointer;">
                    <div class="indicator-header">
                        <div class="indicator-icon">
                            <i class="fas fa-user-lock"></i>
                        </div>
                    </div>

                    <div class="indicator-count"><?= $faltasPendientes['total'] ?></div>
                    <div class="indicator-info">
                        <div class="indicator-titulo">
                            Faltas/Ausencias Pendientes
                        </div>

                        <div class="indicator-meta">
                            <span class="indicator-status faltas-indicador <?= $faltasPendientes['color'] ?>">
                                <?php
                                $diasRestantes = $faltasPendientes['dias_restantes'];
                                if ($faltasPendientes['total'] == 0) {
                                    echo 'Al día';
                                } elseif ($diasRestantes < 0) {
                                    echo 'Vencido hace ' . abs($diasRestantes) . ' días';
                                } elseif ($diasRestantes === 0) {
                                    echo 'Vence hoy';
                                } else {
                                    echo $diasRestantes . ' días restantes';
                                }
                                ?>
                            </span>
                            <span class="indicator-action">
                                <i class="fas fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </div>


                <!-- Indicador de Horario Pendiente - ENLACE DIRECTO -->

                <a href="<?= $estadoHorario['url'] ?>" style="text-decoration: none; display: block;">
                    <div class="indicator-container" style="cursor: pointer;">
                        <div class="indicator-header">
                            <div class="indicator-icon">
                                <i class="far fa-calendar-alt"></i>
                            </div>
                        </div>
                        <div class="indicator-count">
                            <?php
                            if ($estadoHorario['estado'] == 'completo') {
                                echo '<i class="fas fa-check" style="font-size: 2.5rem;"></i>';
                            } else {
                                echo count($estadoHorario['sucursales_sin_horario']);
                            }
                            ?>
                        </div>
                        <div class="indicator-info">
                            <div class="indicator-titulo">
                                Horario Semana <?= $estadoHorario['semana_siguiente']['numero_semana'] ?? 'Siguiente' ?>
                            </div>
                            <div class="indicator-meta">
                                <span class="indicator-status horario-indicador <?= $estadoHorario['color'] ?>">
                                    <?php
                                    if ($estadoHorario['estado'] == 'completo') {
                                        echo $estadoHorario['periodo_activo'] ? 'Completo - Modificar' : 'Completo';
                                    } elseif ($estadoHorario['estado'] == 'fuera_periodo') {
                                        echo 'Fuera de período';
                                    } elseif ($estadoHorario['estado'] == 'sin_sucursales') {
                                        echo 'Sin sucursales';
                                    } else {
                                        $diasRestantes = $estadoHorario['dias_restantes'];
                                        if ($diasRestantes < 0) {
                                            echo 'Vencido';
                                        } elseif ($diasRestantes === 0) {
                                            echo 'Vence hoy';
                                        } else {
                                            echo $diasRestantes . ' días restantes';
                                        }
                                    }
                                    ?>
                                </span>
                                <span class="indicator-action">
                                    <i class="fas fa-arrow-right"></i>
                                </span>
                            </div>


                        </div>

                    </div>
                </a>

            </div>



            <!-- Modal de Detalles de Tardanzas Pendientes -->
            <div id="modalTardanzas" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 90%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-list"></i> Detalles de Tardanzas Pendientes de Reportar</h3>
                        <span class="close-modal" onclick="cerrarModalTardanzas()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtros-modal"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <strong>Periodo:</strong>
                                <?= date('d/m/Y', strtotime($tardanzasPendientes['fecha_desde'])) ?> -
                                <?= date('d/m/Y', strtotime($tardanzasPendientes['fecha_hasta'])) ?>
                                | <strong>Total:</strong> <?= $tardanzasPendientes['total'] ?> tardanzas
                                <?php
                                $diasRestantes = $tardanzasPendientes['dias_restantes'];
                                if ($diasRestantes < 0) {
                                    echo "<span style='color: #dc3545;'> (Vencido hace " . abs($diasRestantes) . " días)</span>";
                                } elseif ($diasRestantes === 0) {
                                    echo "<span style='color: #dc3545;'> (Vence hoy)</span>";
                                } else {
                                    echo " (" . $diasRestantes . " días restantes)";
                                }
                                ?>
                            </div>
                            <a href="<?= $tardanzasPendientes['url_tardanzas'] ?>" class="btn-ver-detalles"
                                target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ir a Reportar Tardanzas
                            </a>
                        </div>

                        <?php if (empty($tardanzasPendientes['detalles'])): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle"
                                    style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                                <h4>No hay tardanzas pendientes de reportar</h4>
                                <p>Todas las tardanzas han sido reportadas correctamente.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto; max-height: 60vh;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr
                                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                            <th style="padding: 12px; text-align: left;">Colaborador</th>
                                            <th style="padding: 12px; text-align: center;">Sucursal</th>
                                            <th style="padding: 12px; text-align: center;">Fecha</th>
                                            <th style="padding: 12px; text-align: center;">Horario Programado</th>
                                            <th style="padding: 12px; text-align: center;">Hora Marcada</th>
                                            <th style="padding: 12px; text-align: center;">Minutos de Tardanza</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tardanzasPendientes['detalles'] as $index => $tardanza): ?>
                                            <tr style="background: <?= $index % 2 === 0 ? '#f8f9fa' : 'white' ?>;">
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                                                    <strong><?= htmlspecialchars($tardanza['nombre_completo']) ?></strong>
                                                    <br><small>Código: <?= $tardanza['CodOperario'] ?></small>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= htmlspecialchars($tardanza['sucursal_nombre']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoFecha($tardanza['fecha']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= $tardanza['hora_programada'] ? formatoHoraAmPm($tardanza['hora_programada']) : 'N/A' ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoHoraAmPm($tardanza['hora_ingreso']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <span style="color: #dc3545; font-weight: bold;">
                                                        +<?= $tardanza['minutos_tardanza'] ?> min
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Modal de Detalles de Faltas Pendientes -->
            <div id="modalFaltas" class="modal-pendientes">
                <div class="modal-content-pendientes" style="max-width: 90%;">
                    <div class="modal-header-pendientes">
                        <h3><i class="fas fa-list"></i> Detalles de Faltas Pendientes de Reportar</h3>
                        <span class="close-modal" onclick="cerrarModalFaltas()">&times;</span>
                    </div>
                    <div class="modal-body-pendientes">
                        <div class="filtros-modal"
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <strong>Periodo:</strong>
                                <?= date('d/m/Y', strtotime($faltasPendientes['fecha_desde'])) ?> -
                                <?= date('d/m/Y', strtotime($faltasPendientes['fecha_hasta'])) ?>
                                | <strong>Total:</strong> <?= $faltasPendientes['total'] ?> faltas
                                <?php
                                $diasRestantes = $faltasPendientes['dias_restantes'];
                                if ($diasRestantes < 0) {
                                    echo "<span style='color: #dc3545;'> (Vencido hace " . abs($diasRestantes) . " días)</span>";
                                } elseif ($diasRestantes === 0) {
                                    echo "<span style='color: #dc3545;'> (Vence hoy)</span>";
                                } else {
                                    echo " (" . $diasRestantes . " días restantes)";
                                }
                                ?>
                            </div>
                            <a href="<?= $faltasPendientes['url_faltas'] ?>" class="btn-ver-detalles" target="_blank">
                                <i class="fas fa-external-link-alt"></i> Ir a Reportar Faltas
                            </a>
                        </div>

                        <?php if (empty($faltasPendientes['detalles'])): ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-check-circle"
                                    style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                                <h4>No hay faltas pendientes de reportar</h4>
                                <p>Todas las ausencias han sido reportadas correctamente.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto; max-height: 60vh;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr
                                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                            <th style="padding: 12px; text-align: left;">Colaborador</th>
                                            <th style="padding: 12px; text-align: center;">Sucursal</th>
                                            <th style="padding: 12px; text-align: center;">Fecha</th>
                                            <th style="padding: 12px; text-align: center;">Horario Programado</th>
                                            <th style="padding: 12px; text-align: center;">Estado Día</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($faltasPendientes['detalles'] as $index => $falta): ?>
                                            <tr style="background: <?= $index % 2 === 0 ? '#f8f9fa' : 'white' ?>;">
                                                <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                                                    <strong><?= htmlspecialchars($falta['nombre_completo']) ?></strong>
                                                    <br><small>Código: <?= $falta['cod_operario'] ?></small>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= htmlspecialchars($falta['sucursal_nombre']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= formatoFecha($falta['fecha']) ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <?= $falta['hora_entrada_programada'] ? formatoHoraAmPm($falta['hora_entrada_programada']) : 'N/A' ?>
                                                    -
                                                    <?= $falta['hora_salida_programada'] ? formatoHoraAmPm($falta['hora_salida_programada']) : 'N/A' ?>
                                                </td>
                                                <td
                                                    style="padding: 10px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                                    <span style="color: #dc3545; font-weight: bold;">
                                                        Ausente
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>


            <!-- Sección: Balances -->
            <h2 class="section-title">
                <i class="fas fa-chart-bar"></i> Balances
            </h2>

            <div class="balances-container">
                <!-- Tarjeta de Ventas vs Meta -->
                <div class="balance-card">
                    <div class="balance-card-body">
                        <div class="ventas-scroll-container">
                            <button class="scroll-btn scroll-btn-left" id="scrollLeft">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="ventas-table-wrapper" id="ventasTableWrapper">
                                <table class="ventas-meta-table" id="ventasMetaTable">
                                    <thead>
                                        <tr id="ventasMetaHeader">
                                            <!-- Generado dinámicamente por JS -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr id="ventasReales">
                                            <!-- Generado dinámicamente por JS -->
                                        </tr>
                                        <tr id="cumplimientoRow">
                                            <!-- Generado dinámicamente por JS -->
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button class="scroll-btn scroll-btn-right" id="scrollRight">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Sección: Accesos Rápidos -->
            <h2 class="section-title">
                <i class="fas fa-bolt"></i> Accesos Rápidos
            </h2>




        </div>
    </div>




    <script src="js/lideres.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>