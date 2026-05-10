<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

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

// Conexión a la base de datos
try {
    // $conn = conectarDB(); // Comentado por migración al core
    $conn = $conn;
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener el ID si se está viendo un detalle
$faltanteId = $_GET['id'] ?? 0;

// Si hay un ID específico, mostrar el detalle
if ($faltanteId) {
    mostrarDetalleFaltanteCaja($conn, $faltanteId);
} else {
    mostrarListadoFaltantesCaja($conn);
}

function mostrarListadoFaltantesCaja($conn) {
    // Paginación
    $pagina = $_GET['pagina'] ?? 1;
    $porPagina = 10;
    $inicio = ($pagina - 1) * $porPagina;

    // Obtener total de registros
    $total = $conn->query("SELECT COUNT(*) FROM faltante_caja")->fetchColumn();
    $totalPaginas = ceil($total / $porPagina);

    // Obtener faltantes de caja con nombre del registrador desde Operarios
    $stmt = $conn->prepare("SELECT fc.*, s.nombre as sucursal_nombre,
                           CONCAT(
                               IFNULL(o.Nombre, ''), ' ', 
                               IFNULL(o.Nombre2, ''), ' ', 
                               IFNULL(o.Apellido, ''), ' ', 
                               IFNULL(o.Apellido2, '')
                           ) as registrador_nombre
                           FROM faltante_caja fc
                           JOIN sucursales s ON fc.sucursal_id = s.codigo
                           LEFT JOIN Operarios o ON fc.registrador_id = o.CodOperario
                           ORDER BY fc.fecha DESC, fc.id DESC
                           LIMIT :inicio, :porPagina");
    $stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
    $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $faltantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mostrar HTML
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Historial de Faltantes de Caja</title>
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
            <h1>Historial de Faltantes de Caja</h1>
            
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Sucursal</th>
                        <th>Colaborador</th>
                        <th>Monto (C$)</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faltantes)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No se encontraron registros de faltante de caja</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($faltantes as $faltante): ?>
                            <tr>
                                <td><?= formatoFecha($faltante['fecha']) ?></td>
                                <td><?= htmlspecialchars($faltante['sucursal_nombre']) ?></td>
                                <td>
                                    <?= htmlspecialchars($faltante['operario_nombre']) ?>
                                    <span style="color: #666; font-size: 0.9em;">(<?= $faltante['operario_id'] ?>)</span>
                                </td>
                                <td style="color: red; font-weight: bold;">
                                    C$ <?= number_format($faltante['monto'], 2) ?>
                                </td>
                                <td><?= htmlspecialchars($faltante['registrador_nombre'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="?id=<?= $faltante['id'] ?>" class="btn" title="Ver detalles">
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

function mostrarDetalleFaltanteCaja($conn, $id) {
    // Obtener datos principales con nombre del registrador desde Operarios
    $stmt = $conn->prepare("SELECT fc.*, s.nombre as sucursal_nombre,
                           CONCAT(
                               IFNULL(o.Nombre, ''), ' ', 
                               IFNULL(o.Nombre2, ''), ' ', 
                               IFNULL(o.Apellido, ''), ' ', 
                               IFNULL(o.Apellido2, '')
                           ) as registrador_nombre
                           FROM faltante_caja fc
                           JOIN sucursales s ON fc.sucursal_id = s.codigo
                           LEFT JOIN Operarios o ON fc.registrador_id = o.CodOperario
                           WHERE fc.id = ?");
    $stmt->execute([$id]);
    $faltante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faltante) {
        die("Registro de faltante de caja no encontrado");
    }

    // Formatear fecha y hora de creación (restar 6 horas)
    $fechaRegistro = '';
    if (!empty($faltante['created_at'])) {
        $fechaObj = new DateTime($faltante['created_at']);
        $fechaObj->modify('-6 hours');
        $fechaRegistro = $fechaObj->format('d-m-Y H:i');
    }

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle Faltante de Caja #<?= $id ?></title>
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
            
            .monto-destacado {
                font-size: 24px;
                font-weight: bold;
                color: #e74c3c;
                text-align: center;
                padding: 20px;
                background-color: #f8f9fa;
                border-radius: 5px;
                margin: 20px 0;
            }
            
            .comentarios.sin-comentarios {
                background-color: #f8f9fa;
                border: 1px dashed #ccc;
                opacity: 0.7;
            }
            
            .texto-sin-comentarios {
                color: #6c757d;
                font-style: italic;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .texto-sin-comentarios i {
                color: #6c757d;
            }
        </style>
    </head>
    <body>
        <!-- Header replicado de ver_auditorias_caja_chica.php -->
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
            <h3 style="text-align:center;">Faltante de Caja #<?= $id ?></h3>
            
            <div class="card">
                <div class="card-title">Detalles del Faltante</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Fecha del Faltante:</div>
                        <div class="info-value"><?= formatoFecha($faltante['fecha']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Sucursal:</div>
                        <div class="info-value"><?= htmlspecialchars($faltante['sucursal_nombre']) ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Colaborador:</div>
                        <div class="info-value">
                            <?= htmlspecialchars($faltante['operario_nombre']) ?>
                            <br><small style="color: #666;">Código: <?= $faltante['operario_id'] ?></small>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Registrado por:</div>
                        <div class="info-value"><?= htmlspecialchars($faltante['registrador_nombre'] ?? 'N/A') ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Fecha de Registro:</div>
                        <div class="info-value">
                            <?= $fechaRegistro ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="monto-destacado">
                Monto del Faltante: C$ <?= number_format($faltante['monto'], 2) ?>
            </div>
            
            <div class="comentarios <?= empty($faltante['comentarios']) ? 'sin-comentarios' : '' ?>">
                <div class="info-label">Comentarios:</div>
                <div class="info-value">
                    <?php if (!empty($faltante['comentarios'])): ?>
                        <?= nl2br(htmlspecialchars($faltante['comentarios'])) ?>
                    <?php else: ?>
                        <span class="texto-sin-comentarios">
                            <i class="fas fa-info-circle"></i> No hay comentarios registrados para este faltante
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="auditorias_consolidadas.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Volver al Historial
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
