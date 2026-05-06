<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener el ID de la auditoría
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de auditoría no válido");
}

$id = intval($_GET['id']);

// Obtener los datos de la auditoría
try {
    $stmt = $conn->prepare("SELECT * FROM auditoria_procesos WHERE id = ?");
    $stmt->execute([$id]);
    $auditoria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$auditoria) {
        die("Auditoría no encontrada");
    }
    
    // Obtener el nombre del usuario que registró
    $stmt_usuario = $conn->prepare("
        SELECT CONCAT(Nombre, ' ', Apellido) as nombre_completo 
        FROM Operarios 
        WHERE CodOperario = ?
    ");
    $stmt_usuario->execute([$auditoria['usuario_id']]);
    $usuario_registro = $stmt_usuario->fetch();
    $nombre_registrador = $usuario_registro['nombre_completo'] ?? 'Desconocido';
    
} catch (PDOException $e) {
    die("Error al obtener la auditoría: " . $e->getMessage());
}

// Definir los nombres de los items
$items_nombres = [
    1 => 'Lavarse las manos antes de la preparación de los productos',
    2 => 'Se prepara los productos una vez facturado',
    3 => 'Hace más de 2 recorridos en la preparación',
    4 => 'Aplica líquido con el vaso medidor (leche, naranja y agua)',
    5 => 'Medí el azúcar con el jigger',
    6 => 'Sigue el proceso de embasado cuando el motor está operando',
    7 => 'Se entrega batido con la consistencia establecida por operaciones',
    8 => 'Limpia la estación de trabajo después de preparar',
    9 => 'Sigue el proceso de decoración (Waffle)',
    10 => 'Usa la waflera en la temperatura y tiempo establecida (3.5 g)',
    11 => 'Usa la tabla para picar frutas',
    12 => 'Coloca papel toalla al finalizar la preparación de los wafles'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Auditoría de Procesos</title>
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 15px;
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
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #0E544C;
        }
        
        h1 {
            color: black;
            margin: 20px 0;
            text-align: center;
            width: 100%;
        }
        
        .info-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            padding: 8px;
            background-color: white;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .items-container {
            margin: 20px 0;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .item-card {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: #f9f9f9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .item-check {
            color: <?php echo $auditoria["item_1"] ? '#28a745' : '#dc3545'; ?>;
            font-size: 18px;
        }
        
        .item-text {
            flex-grow: 1;
        }
        
        .stats-box {
            background-color: #e9f7ef;
            border: 1px solid #d4edda;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .percentage {
            font-size: 36px;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }
        
        .observations-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .observations-box h3 {
            color: #856404;
            margin-top: 0;
        }
        
        .btn-volver {
            background-color: #0E544C;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-block;
            text-decoration: none;
            margin-top: 20px;
            text-align: center;
        }
        
        .btn-volver:hover {
            background-color: #0a3b36;
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
            
            .logo-container {
                order: 1;
                margin-right: 0;
            }
            
            .user-info {
                order: 2;
                margin-left: auto;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="index.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'activo' : '' ?>">
                        <i class="fas fa-clipboard-check"></i> <span class="btn-text">Historial</span>
                    </a>
                    
                    <?php if (verificarAccesoCargo([16])): ?>
                        <a href="agregar.php" class="btn-agregar"><i class="fas fa-cash-register"></i> Auditoría Limpieza</a>
                        <a href="agregarpersonal.php" class="btn-agregar"><i class="fas fa-wallet"></i> Auditoría Personal</a>
                        <a href="agregarservicio.php" class="btn-agregar"><i class="fas fa-boxes"></i> Auditoría Servicio</a>
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= $esAdmin ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= $esAdmin ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="../../../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <h1>Detalles de Auditoría de Procesos</h1>
        
        <div class="info-box">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">ID de Auditoría:</div>
                    <div class="info-value"><?php echo htmlspecialchars($auditoria['id']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Fecha:</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($auditoria['fecha'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Sucursal:</div>
                    <div class="info-value"><?php echo htmlspecialchars($auditoria['sucursal_nombre']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Colaborador Evaluado:</div>
                    <div class="info-value"><?php echo htmlspecialchars($auditoria['operario_nombre']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Registrado por:</div>
                    <div class="info-value"><?php echo htmlspecialchars($nombre_registrador); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Fecha de Registro:</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($auditoria['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <div class="stats-box">
            <h3>Porcentaje de Cumplimiento</h3>
            <div class="percentage"><?php echo $auditoria['porcentaje_cumplimiento']; ?>%</div>
            <p><?php 
                $items_cumplidos = 0;
                for ($i = 1; $i <= 12; $i++) {
                    if ($auditoria["item_$i"]) $items_cumplidos++;
                }
                echo $items_cumplidos . ' de 12 items cumplidos';
            ?></p>
        </div>
        
        <div class="items-container">
            <h3>Items Evaluados</h3>
            <div class="items-grid">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                <div class="item-card">
                    <div class="item-check">
                        <?php if ($auditoria["item_$i"]): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="item-text">
                        <strong>Item <?php echo $i; ?>:</strong><br>
                        <?php echo htmlspecialchars($items_nombres[$i]); ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <?php if (!empty($auditoria['observaciones'])): ?>
        <div class="observations-box">
            <h3>Observaciones</h3>
            <p><?php echo nl2br(htmlspecialchars($auditoria['observaciones'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center;">
            <a href="index.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al Historial
            </a>
        </div>
    </div>
</body>
</html>
