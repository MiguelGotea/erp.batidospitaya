<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
require_once '../../../core/helpers/funciones.php'; 
require_once '../../../core/database/conexion.php'; 

//******************************Estándar para header******************************
verificarAutenticacion();

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
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">

    <!-- Librerías Estándar ERP -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- Estilos Estándar ERP -->
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">

    <style>
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
            .info-grid {
                grid-template-columns: 1fr;
            }

            .items-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Ver Auditoría de Procesos'); ?>

            <div class="container-fluid p-3">

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

            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <!-- jQuery y Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>