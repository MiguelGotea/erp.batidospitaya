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

// Establecer zona horaria
date_default_timezone_set('America/Managua');

// Función para formatear fechas en español
function formatFechaEspanolAuditoria($fecha) {
    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
    ];
    
    $date = new DateTime($fecha);
    $date->modify('-6 hours'); // Restar 6 horas
    return $date->format('d').'-'.$meses[$date->format('n')].'-'.$date->format('y').' '.$date->format('h:i a');
}

// Conexión a la base de datos
$host = 'localhost';
// $host = 'localhost';
// $dbname = 'u839374897_avisos';
// $username = 'u839374897_avisos';
// $password = '8GLVR9*k';

try {
    // Conexión a la base de datos usando PDO y las constantes de config.php
    // $conn = conectarDB(); // Comentado por migración al core
    global $conn;
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener el ID si se está viendo un detalle
$auditoriaId = $_GET['id'] ?? 0;

// Si hay un ID específico, mostrar el detalle
if ($auditoriaId) {
    mostrarDetalleAuditoria($conn, $auditoriaId);
} else {
    mostrarListadoAuditorias($conn);
}

function mostrarListadoAuditorias($conn) {
    // Paginación
    $pagina = $_GET['pagina'] ?? 1;
    $porPagina = 10;
    $inicio = ($pagina - 1) * $porPagina;

    // Obtener total de registros
    $total = $conn->query("SELECT COUNT(*) FROM auditoria_caja_chica")->fetchColumn();
    $totalPaginas = ceil($total / $porPagina);

    // Obtener auditorías
    $stmt = $conn->prepare("SELECT a.*, s.nombre as sucursal_nombre, 
                       CONCAT(o.Nombre, ' ', o.Apellido) as nombre_lider
                       FROM auditoria_caja_chica a
                       JOIN sucursales s ON a.sucursal_id = s.codigo
                       LEFT JOIN Operarios o ON a.lider_tienda_codigo = o.CodOperario
                       ORDER BY a.fecha_hora_regsys DESC
                       LIMIT :inicio, :porPagina");
    $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mostrar HTML
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Historial de Auditorías de Caja Chica</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="icon" href="icon12.png" type="image/png">
        <link rel="stylesheet" href="css/ver_auditorias_caja_chica.css?v=<?php echo mt_rand(1, 10000); ?>">
    </head>
    <body>
        <div class="header">
            <img src="Logo.svg" alt="Logo">
        </div>
        
        <div class="container">
            <h1>Historial de Auditorías de Caja Chica</h1>
            
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Sucursal</th>
                        <th>Líder</th>
                        <th>Monto Designado</th>
                        <th>Total Conteo</th>
                        <th>Diferencia</th>
                        <th>Auditor</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditorias)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No se encontraron auditorías</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditorias as $auditoria): 
                            $diferencia = $auditoria['monto_designado'] - $auditoria['total_conteo'];
                            $claseBadge = ($diferencia > 0) ? 'badge-danger' : (($diferencia < 0) ? 'badge-warning' : 'badge-success');
                            $textoDiferencia = ($diferencia > 0) ? 'Faltante' : (($diferencia < 0) ? 'Sobrante' : 'Correcto');
                        ?>
                            <tr>
                                <td><?= formatFechaEspanolAuditoria($auditoria['fecha_hora_regsys']) ?></td>
                                <td><?= htmlspecialchars($auditoria['sucursal_nombre']) ?></td>
                                <td>
                                    <?= !empty($auditoria['nombre_lider']) ? htmlspecialchars($auditoria['nombre_lider']) : htmlspecialchars($auditoria['lider_tienda']) ?>
                                    <?php if (!empty($auditoria['lider_tienda_codigo'])): ?>
                                        <span style="color: #666; font-size: 0.9em;">(<?= $auditoria['lider_tienda_codigo'] ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>C$ <?= number_format($auditoria['monto_designado'], 2) ?></td>
                                <td>C$ <?= number_format($auditoria['total_conteo'], 2) ?></td>
                                <td style="color: <?= $diferencia > 0 ? 'red' : ($diferencia < 0 ? '#ffc107' : 'green') ?>">
                                    C$ <?= number_format(abs($diferencia), 2) ?>
                                </td>
                                <td><?= htmlspecialchars($auditoria['auditor']) ?></td>
                                <td>
                                    <a href="?id=<?= $auditoria['id'] ?>" class="btn" title="Ver detalles">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPaginas > 1): ?>
            <div class="paginacion">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <a href="?pagina=<?= $i ?>" class="pagina <?= $i == $pagina ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

function mostrarDetalleAuditoria($conn, $id) {
    // Obtener datos principales
    $stmt = $conn->prepare("SELECT a.*, s.nombre as sucursal_nombre, 
                           CONCAT(o.Nombre, ' ', o.Apellido) as nombre_lider,
                           o.CodOperario as lider_codigo
                           FROM auditoria_caja_chica a
                           JOIN sucursales s ON a.sucursal_id = s.codigo
                           LEFT JOIN Operarios o ON a.lider_tienda_codigo = o.CodOperario
                           WHERE a.id = ?");
    $stmt->execute([$id]);
    $auditoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auditoria) {
        die("Auditoría no encontrada");
    }

    // Obtener detalles de denominaciones
    $stmtDetalle = $conn->prepare("SELECT * FROM auditoria_caja_chica_detalle 
                                 WHERE auditoria_id = ? 
                                 ORDER BY denominacion DESC");
    $stmtDetalle->execute([$id]);
    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

    // Calcular diferencia
    $diferencia = $auditoria['monto_designado'] - $auditoria['total_conteo'];
    $claseDiferencia = ($diferencia > 0) ? 'text-danger' : (($diferencia < 0) ? 'text-warning' : 'text-success');
    $textoDiferencia = ($diferencia > 0) ? 'Faltante' : (($diferencia < 0) ? 'Sobrante' : 'Correcto');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle Auditoría Caja Chica #<?= $id ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Calibri&display=swap" rel="stylesheet">
        <link rel="icon" href="icon12.png" type="image/png">
        <link rel="stylesheet" href="css/ver_auditorias_caja_chica.css?v=<?php echo mt_rand(1, 10000); ?>">
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
            <h3 style="text-align:center;">Auditoría Caja Chica #<?= $id ?></h3>
            
            <div class="card">
                <div class="card-title">Detalles de la Auditoría</div>
                <p><strong>Fecha:</strong> <?= formatFechaEspanolAuditoria($auditoria['fecha_hora_regsys']) ?></p>
                <p><strong>Sucursal:</strong> <?= htmlspecialchars($auditoria['sucursal_nombre']) ?></p>
                <p><strong>Líder de Tienda:</strong> 
                    <?= !empty($auditoria['nombre_lider']) ? htmlspecialchars($auditoria['nombre_lider']) : htmlspecialchars($auditoria['lider_tienda']) ?>
                    <?php if (!empty($auditoria['lider_codigo'])): ?>
                        <span style="color: #666;">(<?= $auditoria['lider_codigo'] ?>)</span>
                    <?php endif; ?>
                </p>
                <p style="display:none;"><strong>Auditor:</strong> <?= htmlspecialchars($auditoria['auditor']) ?></p>
            </div>
            
            <div class="summary">
                <div class="summary-item">
                    <h3>Resumen</h3>
                    <p><strong>Monto Designado:</strong> C$ <?= number_format($auditoria['monto_designado'], 2) ?></p>
                    <p><strong>Total Conteo:</strong> C$ <?= number_format($auditoria['total_conteo'], 2) ?></p>
                    <p><strong>Diferencia:</strong> 
                        <span style="color: <?= $diferencia > 0 ? 'red' : ($diferencia < 0 ? '#ffc107' : 'green') ?>">
                            C$ <?= number_format(abs($diferencia), 2) ?>
                        </span>
                    </p>
                </div>
                
                <?php if (!empty($auditoria['comentarios'])): ?>
                <div class="summary-item">
                    <h3>Comentarios</h3>
                    <p><?= nl2br(htmlspecialchars($auditoria['comentarios'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <h2>Conteo de Efectivo</h2>
            <table>
                <thead>
                    <tr style="display:none;">
                        <th>Denominación</th>
                        <th>Cantidad</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td>C$ <?= number_format($detalle['denominacion'], 2) ?></td>
                            <td><?= $detalle['cantidad'] ?></td>
                            <td>C$ <?= number_format($detalle['total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="2">Monto Designado</td>
                        <td>C$ <?= number_format($auditoria['monto_designado'], 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2">Total Conteo</td>
                        <td>C$ <?= number_format($auditoria['total_conteo'], 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2">Diferencia: <span class="<?= $claseDiferencia ?>"><?= $textoDiferencia ?></span></td>
                        <td class="<?= $claseDiferencia ?>">
                            C$ <?= number_format(abs($diferencia), 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Mostrar foto de evidencia si existe -->
            <?php if (!empty($auditoria['foto_path'])): ?>
            <div class="foto-evidencia">
                <div class="foto-titulo">Foto de Insumos Importantes:</div>
                <img src="<?php echo htmlspecialchars($auditoria['foto_path']); ?>" alt="Foto de evidencia Insumos Importantes">
            </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}
?>
