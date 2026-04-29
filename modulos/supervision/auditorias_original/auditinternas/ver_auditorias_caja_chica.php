<?php
// Incluir configuración y verificar autenticación
require_once '../auth.php';
require_once '../funciones.php';
require_once 'config.php';

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
$dbname = 'u839374897_avisos';
$username = 'u839374897_avisos';
$password = '8GLVR9*k';

try {
    // Conexión a la base de datos usando PDO y las constantes de config.php
    $conn = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
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
        <style>
            :root {
                --color-primario: #0E544C;
                --color-secundario: #51B8AC;
                --color-fondo: #F6F6F6;
                --color-texto: #333;
                --color-borde: #ddd;
                --color-error: #dc3545;
                --color-exito: #28a745;
                --color-advertencia: #ffc107;
            }
            
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-size: clamp(11px, 2vw, 16px) !important;
            }
            
            body {
                font-family: 'Calibri', Arial, sans-serif;
                background-color: var(--color-fondo);
                color: var(--color-texto);
                line-height: 1.6;
                overflow-x: hidden;
            }
            
            .container {
                width: 100%;
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .header {
                padding: 15px 20px;
                display: flex;
                align-items: center;
            }
            
            .header img {
                height: 50px;
            }
            
            h1, h2 {
                color: #0E544C;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: center;
            }
            
            th {
                background-color: #0E544C;
                color: white;
            }
            
            tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            
            .card {
                background-color: #e6f7f5;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            
            .card-title {
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            .btn {
                background-color: #51B8AC;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin: 5px;
                justify-content: center !important;
                width: 100%;
            }
            
            .btn:hover {
                opacity: 0.9;
            }
            
            .summary {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .summary-item {
                flex: 1;
                min-width: 200px;
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .currency-section {
                margin-bottom: 30px;
            }
            
            .currency-title {
                background-color: #0E544C;
                color: white;
                padding: 8px;
                border-radius: 4px 4px 0 0;
                margin-bottom: 0;
            }
            
            .photo-container {
                margin-top: 20px;
                text-align: center;
            }
            
            .photo-container img {
                max-width: 100%;
                max-height: 400px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .photo-title {
                font-weight: bold;
                margin-bottom: 10px;
                color: #0E544C;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 25px;
            }
            
            .info-item {
                margin-bottom: 15px;
            }
            
            .info-label {
                font-weight: bold;
                color: var(--color-primario);
                margin-bottom: 5px;
            }
            .text-danger {
                color: var(--color-error);
            }
            
            .text-warning {
                color: var(--color-advertencia);
            }
            
            .text-success {
                color: var(--color-exito);
            }
            
            .total-row {
                font-weight: bold;
                background-color: #f8f9fa;
            }
            
            .comentarios {
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid var(--color-borde);
            }
            
            .comentarios .info-label {
                margin-bottom: 10px;
            }
            
            .comentarios .info-value {
                white-space: pre-wrap;
                line-height: 1.6;
            }
            
            @media (max-width: 768px) {
                th, td {
                    padding: 5px;
                }
                
                .summary {
                    flex-direction: column;
                }
                
                .btn {
                    padding: 6px 10px;
                }
                
                .container {
                    padding: 10px;
                }
            }
        </style>
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
        <style>
            :root {
                --color-primario: #0E544C;
                --color-secundario: #51B8AC;
                --color-fondo: #F6F6F6;
                --color-texto: #333;
                --color-borde: #ddd;
                --color-error: #dc3545;
                --color-exito: #28a745;
                --color-advertencia: #ffc107;
            }
                
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
                
            body {
                font-family: 'Calibri', Arial, sans-serif;
                background-color: var(--color-fondo);
                color: var(--color-texto);
                line-height: 1.6;
                overflow-x: hidden;
            }
                
            .container {
                width: 100%;
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }
                
                .header img {
                    height: 50px;
                }
                
            h1, h2 {
                color: #0E544C;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: center;
            }
            
            th {
                background-color: #0E544C;
                color: white;
            }
            
            tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            
            .card {
                background-color: #e6f7f5;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            
            .card-title {
                font-weight: bold;
                margin-bottom: 10px;
            }
            
            .btn {
                background-color: #51B8AC;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin: 5px;
            }
            
            .btn:hover {
                opacity: 0.9;
            }
            
            .summary {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .summary-item {
                flex: 1;
                min-width: 200px;
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .currency-section {
                margin-bottom: 30px;
            }
            
            .currency-title {
                background-color: #0E544C;
                color: white;
                padding: 8px;
                border-radius: 4px 4px 0 0;
                margin-bottom: 0;
            }
            
            .photo-container {
                margin-top: 20px;
                text-align: center;
            }
            
            .photo-container img {
                max-width: 100%;
                max-height: 400px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .photo-title {
                font-weight: bold;
                margin-bottom: 10px;
                color: #0E544C;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 25px;
            }
            
            .info-item {
                margin-bottom: 15px;
            }
            
            .info-label {
                font-weight: bold;
                color: var(--color-primario);
                margin-bottom: 5px;
            }
            
            .text-danger {
                color: var(--color-error);
            }
            
            .text-warning {
                color: var(--color-advertencia);
            }
            
            .text-success {
                color: var(--color-exito);
            }
            
            .total-row {
                font-weight: bold;
                background-color: #f8f9fa;
            }
            
            .comentarios {
                margin-top: 25px;
                padding-top: 15px;
                border-top: 1px solid var(--color-borde);
            }
            
            .comentarios .info-label {
                margin-bottom: 10px;
            }
            
            .comentarios .info-value {
                white-space: pre-wrap;
                line-height: 1.6;
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
    
                th, td {
                    padding: 5px;
                }
                
                .summary {
                    flex-direction: column;
                }
                
                .btn {
                    padding: 6px 10px;
                }
                
                .container {
                    padding: 10px;
                }
                .foto-evidencia img {
                    max-height: 300px;
                }
            }
            
            .foto-evidencia {
                margin-top: 20px;
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 4px;
                max-width: 100%;
            }
            
            .foto-evidencia img {
                max-width: 50%;
                max-height: 500px;
                display: block;
                margin: 10px auto;
                border: 1px solid #ddd;
            }
            
            .foto-titulo {
                font-weight: bold;
                margin-bottom: 10px;
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