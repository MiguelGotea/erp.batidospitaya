<?php
// ver_auditoria_promociones.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// $db = conectarDB();
$db = $conn;

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo (permiso de vista o vista_interna)
if (!tienePermiso('auditorias_desempeno', 'vista', $cargoOperario) && !tienePermiso('auditorias_desempeno', 'vista_interna', $cargoOperario) && !$esAdmin) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener ID de la auditoría
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: ../index.php');
    exit();
}

// Obtener datos de la auditoría
try {
    $stmt = $db->prepare("
        SELECT ap.*, 
               o.Nombre as evaluador_nombre,
               o.Apellido as evaluador_apellido
        FROM auditoria_promociones ap
        LEFT JOIN Operarios o ON ap.usuario_id = o.CodOperario
        WHERE ap.id = ?
    ");
    $stmt->execute([$id]);
    $auditoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auditoria) {
        header('Location: ../index.php?error=no_encontrado');
        exit();
    }
} catch (PDOException $e) {
    die("Error al obtener la auditoría: " . $e->getMessage());
}

// Preguntas de la auditoría
$preguntas = [
    1 => 'Mencione nombres y cantidad de combos o promociones activas.',
    2 => '¿Cuál es la vigencia de estos combos o promociones?',
    3 => 'Mencione si hay restricciones.',
    4 => '¿Precios de cada combo?',
    5 => 'Detalle en qué consiste cada combo o promoción.'
];

// Formatear fecha
$fechaFormateada = date('d/m/Y H:i', strtotime($auditoria['fecha']));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Auditoría de Promociones - #<?php echo $id; ?></title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/ver_auditoria_promociones.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="/core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                <a href="../index.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver al Historial
                </a>
            </div>
        </header>

        <h1><i class="fas fa-tags"></i> Auditoría de Promociones Combos Pitaya</h1>

        <!-- Información General -->
        <div class="info-card">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Fecha y Hora</div>
                    <div class="info-value"><?php echo $fechaFormateada; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Sucursal</div>
                    <div class="info-value"><?php echo htmlspecialchars($auditoria['sucursal_nombre']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Colaborador Evaluado</div>
                    <div class="info-value"><?php echo htmlspecialchars($auditoria['operario_nombre']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Evaluador</div>
                    <div class="info-value">
                        <?php
                        if ($auditoria['evaluador_nombre']) {
                            echo htmlspecialchars($auditoria['evaluador_nombre'] . ' ' . $auditoria['evaluador_apellido']);
                        } else {
                            echo 'Administrador';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-box">
            <h3>PORCENTAJE DE CUMPLIMIENTO</h3>
            <div class="percentage-big"><?php echo $auditoria['porcentaje_cumplimiento']; ?>%</div>
            <div class="percentage-label">
                <?php
                $respondidas = 0;
                for ($i = 1; $i <= 5; $i++) {
                    if (!empty($auditoria['respuesta_' . $i])) $respondidas++;
                }
                echo "$respondidas de 5 preguntas respondidas";
                ?>
            </div>
        </div>

        <!-- Preguntas y Respuestas -->
        <div class="preguntas-section">
            <h2><i class="fas fa-clipboard-list"></i> Preguntas y Respuestas</h2>

            <?php foreach ($preguntas as $num => $preguntaTexto): ?>
                <div class="pregunta-card">
                    <div class="pregunta-header">
                        <span class="pregunta-numero"><?php echo $num; ?></span>
                        <span class="pregunta-texto"><?php echo htmlspecialchars($preguntaTexto); ?></span>
                    </div>
                    <div class="respuesta-container">
                        <?php if (!empty($auditoria['respuesta_' . $num])): ?>
                            <div class="respuesta-texto"><?php echo nl2br(htmlspecialchars($auditoria['respuesta_' . $num])); ?></div>
                        <?php else: ?>
                            <div class="sin-respuesta"><i class="fas fa-exclamation-circle"></i> Sin respuesta</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Observaciones -->
        <?php if (!empty($auditoria['observaciones'])): ?>
            <div class="observaciones-section">
                <h3><i class="fas fa-sticky-note"></i> Observaciones Adicionales</h3>
                <div class="observaciones-texto"><?php echo nl2br(htmlspecialchars($auditoria['observaciones'])); ?></div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-info">
            <p>
                Auditoría #<?php echo $id; ?> |
                Registrada: <?php echo date('d/m/Y H:i', strtotime($auditoria['created_at'])); ?>
            </p>
            <button onclick="window.print()" class="btn-volver" style="margin-top: 10px;">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
</body>

</html>