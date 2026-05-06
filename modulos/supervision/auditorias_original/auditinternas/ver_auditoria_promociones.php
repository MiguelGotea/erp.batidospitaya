<?php
// ver_auditoria_promociones.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditora
require_once 'config.php';

$db = conectarDB();

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
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
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            box-sizing: border-box;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
            background-color: #F6F6F6;
        }
        
        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            min-height: 100vh;
            box-sizing: border-box;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
        }

        .btn-volver {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .btn-volver:hover {
            background: #0E544C;
            color: white;
        }
        
        h1 {
            color: #0E544C;
            margin: 0 0 25px 0;
            text-align: center;
            width: 100%;
        }
        
        .info-card {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            padding: 10px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #51B8AC;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .stats-box {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
            color: white;
        }
        
        .stats-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .percentage-big {
            font-size: 48px;
            font-weight: bold;
        }
        
        .percentage-label {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .preguntas-section {
            margin-top: 25px;
        }
        
        .preguntas-section h2 {
            color: #0E544C;
            border-bottom: 2px solid #51B8AC;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .pregunta-card {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: box-shadow 0.3s;
        }
        
        .pregunta-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .pregunta-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .pregunta-numero {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #51B8AC;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .pregunta-texto {
            font-weight: 600;
            color: #0E544C;
            line-height: 1.4;
        }
        
        .respuesta-container {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-left: 42px;
            border-left: 3px solid #51B8AC;
        }
        
        .respuesta-texto {
            color: #333;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .sin-respuesta {
            color: #dc3545;
            font-style: italic;
        }
        
        .observaciones-section {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .observaciones-section h3 {
            color: #856404;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .observaciones-texto {
            color: #856404;
            line-height: 1.6;
        }
        
        .footer-info {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 13px;
        }
        
        @media print {
            .btn-volver {
                display: none;
            }
            
            .container {
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .respuesta-container {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
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
