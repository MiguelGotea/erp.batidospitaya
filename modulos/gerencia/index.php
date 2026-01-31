<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/components/ComponentRegistry.php';

// Verificar sesión y obtener usuario
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$codOperario = $usuario['CodOperario'];

// Verificar acceso al módulo usando sistema de permisos
if (!tienePermiso('index_gerencia', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}

// Inicializar ComponentRegistry y cargar componentes
$registry = new Core\Components\ComponentRegistry($conn);
$indicadores = $registry->getIndicatorsForCargo($codOperario, $cargoOperario);
$balances = $registry->getBalancesForCargo($codOperario, $cargoOperario);
$shortcuts = $registry->getShortcutsForCargo($cargoOperario);

// Verificar si el usuario está de cumpleaños
$cumpleanosInfo = verificarCumpleanosUsuario($codOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerencia - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="../../core/assets/css/indexmodulos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/cumpleanos.css?v=<?php echo time(); ?>">



    <!-- CSS Base de Componentes -->
    <link rel="stylesheet" href="../../core/components/indicators/base/indicators.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../core/components/balances/base/balances.css?v=<?php echo time(); ?>">

    <!-- CSS Dinámico de Componentes -->
    <?php
    // Recopilar todos los CSS únicos de los indicadores y balances
    $componentCss = [];
    foreach ($indicadores as $ind) {
        if (!empty($ind['assets']['css'])) {
            $componentCss[] = $ind['assets']['css'];
        }
        if (!empty($ind['assets']['modal_css'])) {
            $componentCss[] = $ind['assets']['modal_css'];
        }
    }
    foreach ($balances as $bal) {
        if (!empty($bal['assets']['css'])) {
            $componentCss[] = $bal['assets']['css'];
        }
    }
    $componentCss = array_unique($componentCss);

    foreach ($componentCss as $cssPath): ?>
        <link rel="stylesheet" href="../../<?= $cssPath ?>?v=<?php echo time(); ?>">
    <?php endforeach; ?>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <!-- Renderizar header universal -->
            <?php echo renderHeader($usuario, false, ''); ?>

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
                                    ¡Feliz Cumpleaños
                                    <?= htmlspecialchars($cumpleanosInfo['nombre']) ?>! 🎉🎂
                                </h2>
                                <p class="cumpleanos-message">
                                    Hoy celebramos tu día especial 🥳 y queremos agradecerte por ser parte de nuestra
                                    familia
                                    en Batidos Pitaya 🍓.
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

            <!-- Indicadores -->
            <?php if (!empty($indicadores)): ?>
                <h2 class="section-title">
                    <i class="fas fa-chart-line"></i> Indicadores
                </h2>
                <div class="indicadores-container">
                    <?php foreach ($indicadores as $ind): ?>
                        <?php include '../../core/components/indicators/base/indicator_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Balances (100% ancho) -->
            <?php if (!empty($balances)): ?>
                <h2 class="section-title">
                    <i class="fas fa-chart-bar"></i> Balances
                </h2>
                <div class="balances-container-full">
                    <?php foreach ($balances as $balance): ?>
                        <?php include '../../core/components/balances/base/balance_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Shortcuts -->
            <?php if (!empty($shortcuts)): ?>
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i> Accesos Rápidos
                </h2>
                <div class="indicadores-container">
                    <?php foreach ($shortcuts as $shortcut): ?>
                        <?php include '../../core/components/shortcuts/base/shortcut_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts Compartidos -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Scripts Dinámicos de Componentes -->
    <?php
    // Recopilar todos los JS únicos
    $componentJs = [];
    foreach ($indicadores as $ind) {
        if (!empty($ind['assets']['js'])) {
            $componentJs[] = $ind['assets']['js'];
        }
        if (!empty($ind['assets']['modal_js'])) {
            $componentJs[] = $ind['assets']['modal_js'];
        }
    }
    foreach ($balances as $bal) {
        if (!empty($bal['assets']['js'])) {
            $componentJs[] = $bal['assets']['js'];
        }
    }
    $componentJs = array_unique($componentJs);

    foreach ($componentJs as $jsPath): ?>
        <script src="../../<?= $jsPath ?>?v=<?php echo time(); ?>"></script>
    <?php endforeach; ?>
</body>

</html>