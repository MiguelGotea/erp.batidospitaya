<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([8, 11, 16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 11, 16, 21])) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Configuración de zona horaria
date_default_timezone_set('America/Managua');

try {
    // Conexión a la base de datos usando las constantes de config.php
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ajuste específico de zona horaria para MySQL
    $pdo->exec("SET time_zone = '-6:00'");
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función específica para este archivo que mantiene el formato original
function formatFechaEspanolFacturacion($fecha = 'now') {
    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
    ];
    
    $date = new DateTime($fecha);
    return $date->format('d').'-'.$meses[$date->format('n')].'-'.$date->format('y').' '.$date->format('h:i a');
}

// Obtener auditorías
$auditorias = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, s.nombre as sucursal_nombre, 
               CONCAT(o.Nombre, ' ', o.Apellido, ' (', o.CodOperario, ')') as nombre_cajero
        FROM auditoria_facturacion a
        JOIN sucursales s ON a.sucursal_id = s.codigo
        LEFT JOIN Operarios o ON a.cajero = o.CodOperario
        ORDER BY a.fecha_hora_regsys DESC
    ");
    $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener auditorías: " . $e->getMessage());
}

// Obtener detalles si hay ID
$detalles = [];
$auditoria_seleccionada = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $auditoria_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, s.nombre as sucursal_nombre,
                   CONCAT(o.Nombre, ' ', o.Apellido, ' (', o.CodOperario, ')') as nombre_cajero
            FROM auditoria_facturacion a
            JOIN sucursales s ON a.sucursal_id = s.codigo
            LEFT JOIN Operarios o ON a.cajero = o.CodOperario
            WHERE a.id = ?
        ");
        $stmt->execute([$auditoria_id]);
        $auditoria_seleccionada = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener detalles: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($auditoria_seleccionada) ? 'Detalle de Auditoría #' . $auditoria_id : 'Historial de Auditorías' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="css/ver_auditorias_facturacion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <div class="container">
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
        
        <h1 style="text-align:center;"><?= isset($auditoria_seleccionada) ? 'Auditoría de Caja Facturación #' . $auditoria_id : 'Historial de Auditorías' ?></h1>
        
        <?php if (isset($auditoria_seleccionada)): ?>
            <div class="card">
                <div class="card-title">Detalles de la Auditoría</div>
                <p><strong>Sucursal:</strong> <?= htmlspecialchars($auditoria_seleccionada['sucursal_nombre']) ?></p>
                <p><strong>Fecha:</strong> <?= formatFechaEspanolFacturacion($auditoria_seleccionada['fecha_hora_regsys']) ?></p>
                <p><strong>Cajero/a:</strong> <?= htmlspecialchars($auditoria_seleccionada['nombre_cajero'] ?? $auditoria_seleccionada['cajero_nombre'] ?? 'N/A') ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-item">
                    <h3 style="text-align:center;">Resumen</h3>
                    <p><strong>Efectivo a Entregar (Sistema):</strong> C$ <?= number_format($auditoria_seleccionada['monto_designado'], 2) ?></p>
                    <p><strong>Efectivo Entregado (Conteo Físico):</strong> C$ <?= number_format($auditoria_seleccionada['total_conteo'], 2) ?></p>
                    <?php 
                        $diferencia = $auditoria_seleccionada['total_conteo'] - $auditoria_seleccionada['monto_designado'];
                    ?>
                    <p><strong>Diferencia:</strong> 
                        <span style="color: <?= $diferencia < 0 ? 'red' : 'green' ?>">
                            C$ <?= number_format($diferencia, 2) ?>
                        </span>
                    </p>
                </div>
                
                <div class="summary-item">
                    <h3>Comentarios</h3>
                    <p><?= !empty($auditoria_seleccionada['comentarios']) ? htmlspecialchars($auditoria_seleccionada['comentarios']) : 'Sin comentarios' ?></p>
                </div>
            </div>
            
            <?php if (!empty($auditoria_seleccionada['foto_path'])): ?>
                <div class="photo-container">
                    <div class="photo-title">Foto de Evidencia</div>
                    <img src="<?= htmlspecialchars($auditoria_seleccionada['foto_path']) ?>" alt="Foto de evidencia de la auditoría">
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Sucursal</th>
                        <th>Cajero</th>
                        <th>Monto Designado</th>
                        <th>Total Conteo</th>
                        <th>Diferencia</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditorias as $auditoria): ?>
                    <tr>
                        <td><?= formatFechaEspanolFacturacion($auditoria['fecha_hora_regsys']) ?></td>
                        <td><?= htmlspecialchars($auditoria['sucursal_nombre']) ?></td>
                        <td><?= htmlspecialchars($auditoria['nombre_cajero'] ?? $auditoria['cajero_nombre'] ?? $auditoria['cajero']) ?></td>
                        <td>C$ <?= number_format($auditoria['monto_designado'], 2) ?></td>
                        <td>C$ <?= number_format($auditoria['total_conteo'], 2) ?></td>
                        <td style="color: <?= $auditoria['faltante_sobrante'] < 0 ? 'red' : 'green' ?>">
                            C$ <?= number_format($auditoria['faltante_sobrante'], 2) ?>
                        </td>
                        <td>
                            <a href="?id=<?= $auditoria['id'] ?>" class="btn" title="Ver detalles">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($auditorias)): ?>
                <p>No se encontraron auditorías registradas.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
