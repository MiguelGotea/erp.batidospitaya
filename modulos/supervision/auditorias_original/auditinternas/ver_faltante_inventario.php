<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core
require_once '../../../../core/helpers/config.php';

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([8, 11, 16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 11, 16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Verificar que se haya proporcionado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: auditorias_consolidadas.php');
    exit;
}

$faltante_id = intval($_GET['id']);

try {
    // $db = conectarDB(); // Comentado por migración al core
    $db = $conn; // Usar la conexión global del core
    
    // Obtener información principal del faltante
    $stmt = $db->prepare("
        SELECT 
            fi.*,
            CONCAT(u.Nombre, ' ', u.Apellido) AS registrador_nombre
        FROM faltante_inventario fi
        LEFT JOIN Operarios u ON fi.registrador_id = u.CodOperario
        WHERE fi.id = ?
    ");
    $stmt->execute([$faltante_id]);
    $faltante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$faltante) {
        header('Location: auditorias_consolidadas.php');
        exit;
    }
    
    // Obtener los detalles de los productos
    $stmt = $db->prepare("
        SELECT * FROM faltante_inventario_detalle 
        WHERE faltante_id = ?
        ORDER BY id
    ");
    $stmt->execute([$faltante_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener los operarios relacionados con sus montos (solo los que tienen monto > 0)
    $stmt = $db->prepare("
        SELECT * FROM faltante_inventario_operarios 
        WHERE faltante_id = ? AND monto != 0
        ORDER BY monto DESC
    ");
    $stmt->execute([$faltante_id]);
    $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular total faltante
    $total_faltante = $faltante['total_faltante'];
    
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Formatear fecha
function formatFechaHora($fecha_hora) {
    if (empty($fecha_hora)) {
        return 'Fecha no disponible';
    }
    
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    try {
        $fecha = new DateTime($fecha_hora);
        $fecha->sub(new DateInterval('PT6H')); // Ajustar a hora de Nicaragua
        
        $dia = $fecha->format('d');
        $mes = $meses[(int)$fecha->format('m') - 1];
        $anio = $fecha->format('y');
        $hora = $fecha->format('H:i');
        
        return "$dia-$mes-$anio $hora";
    } catch (Exception $e) {
        return 'Fecha inválida';
    }
}

// Función para obtener el color de la categoría (soporta nombres viejos y nuevos)
function obtenerColorCategoriaPorNombre($categoria) {
    $colores = [
        // Nombres viejos (registros históricos)
        'Sin categoría' => '#999999',
        'Aprendiz' => '#3498db',
        'Junior' => '#2ecc71',
        'Senior' => '#e67e22',
        'Experto' => '#9b59b6',
        'Maestro' => '#e74c3c',
        
        // Nombres nuevos de NivelesCargos (registros actuales)
        'Líder' => '#e74c3c',
        'Líder interino' => '#e74c3c',
        'Vendedor Asistente de Líder' => '#e74c3c',
        'Vendedor Experto' => '#9b59b6',
        'Vendedor Junior' => '#2ecc71',
        'Vendedor Training' => '#3498db',
        
        // Otros cargos que podrían aparecer
        'Operario' => '#95a5a6',
    ];
    
    return $colores[$categoria] ?? '#999999';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Faltante de Inventario</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="icon" href="../icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            box-sizing: border-box;
        }
        
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            min-height: 100vh;
        }
        
        .btn-volver {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-volver:hover {
            background-color: #0E544C;
        }
        
        .faltante-info {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 150px;
        }
        
        .info-value {
            flex: 1;
        }
        
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        
        th {
            background-color: #51B8AC;
            color: white;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #0E544C;
            color: white;
        }
        
        .operario-categoria {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 5px;
            color: white;
        }
        
        .operario-item {
            margin-bottom: 5px;
            padding: 5px;
            background-color: #f8f9fa;
            border-radius: 4px;
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
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
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
    
        @media (max-width: 768px) {
    .header-container {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }
    
            .info-label {
                min-width: 100%;
                margin-bottom: 5px;
            }
            
            .info-value {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header replicado de faltante_danos.php -->
        <div class="header">
            <header>
                <div class="header-container">
                    <div class="logo-container">
                        <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
                    </div>
                    
                    <div class="buttons-container">
                        <a href="auditorias_consolidadas.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditorias_consolidadas.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Historial</span>
                        </a>
                    </div>
                    
                    <div class="user-info">
                        <div class="user-avatar">
                            <?= isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin' ? 
                                strtoupper(substr(obtenerUsuarioActual()['nombre'], 0, 1)) : 
                                strtoupper(substr(obtenerUsuarioActual()['Nombre'], 0, 1)) ?>
                        </div>
                        <div>
                            <div>
                                <?= isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin' ? 
                                    htmlspecialchars(obtenerUsuarioActual()['nombre']) : 
                                    htmlspecialchars(obtenerUsuarioActual()['Nombre'].' '.obtenerUsuarioActual()['Apellido']) ?>
                            </div>
                            <small>
                                <?= htmlspecialchars(obtenerCargoPrincipalUsuario($_SESSION['usuario_id'])) ?>
                            </small>
                        </div>
                        <a href="auditorias_consolidadas.php" class="btn-logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </header>
        </div>
    
    <div class="container">
        <div class="faltante-info">
            <div class="info-row">
                <div class="info-label">Fecha y Hora:</div>
                <div class="info-value"><?php echo formatFechaHora($faltante['fecha_hora_regsys']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Sucursal:</div>
                <div class="info-value"><?php echo htmlspecialchars($faltante['sucursal']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Registrado por:</div>
                <div class="info-value">
                    <?php 
                        echo $faltante['registrador_nombre'] ? htmlspecialchars($faltante['registrador_nombre']) : 'No registrado';
                    ?>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Total Faltante:</div>
                <div class="info-value">C$ <?php echo number_format($total_faltante, 2); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Comentarios:</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($faltante['comentarios'])); ?></div>
            </div>
        </div>
        
        <h2>Productos faltantes</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Costo Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($detalle['producto']); ?></td>
                        <td><?php echo $detalle['cantidad']; ?></td>
                        <td>C$ <?php echo number_format($detalle['costo_unitario'], 2); ?></td>
                        <td>C$ <?php echo number_format($detalle['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3">Total Faltante:</td>
                        <td>C$ <?php echo number_format($total_faltante, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <?php if (!empty($operarios)): ?>
        <h2>Colaboradores con responsabilidad</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Cargo</th>
                        <th>Categoría</th>
                        <th>Monto Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operarios as $operario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($operario['operario_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($operario['operario_cargo']); ?></td>
                        <td>
                            <span class="operario-categoria" style="background-color: <?php echo obtenerColorCategoriaPorNombre($operario['operario_categoria']); ?>; color: white;">
                                <?php echo htmlspecialchars($operario['operario_categoria']); ?>
                            </span>
                        </td>
                        <td>C$ <?php echo number_format(abs($operario['monto']), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
