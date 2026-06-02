<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../../core/layout/menu_lateral.php';
require_once '../../../../core/layout/header_universal.php';
require_once '../../../../core/permissions/permissions.php';

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
$puede_ver = tienePermiso('auditoria_efectivo', 'vista', $cargoOperario);
if (!$puede_ver) {
    header('Location: ../../../index.php');
    exit();
}
//******************************Estándar para header, termina******************************

// Establecer zona horaria
date_default_timezone_set('America/Managua');

// Conexión a la base de datos
try {
    // $conn = conectarDB(); // Comentado por migración al core
    $conn = $conn;
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
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

function mostrarListadoFaltantesCaja($conn)
{
    global $usuario;
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
        <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
        <link rel="stylesheet" href="css/ver_faltante_caja.css?v=<?php echo mt_rand(1, 10000); ?>">
    </head>

    <body>
        <?php echo renderMenuLateral($cargoOperario); ?>

        <div class="main-container">
            <div class="sub-container">
                <?php echo renderHeader($usuario, 'Historial de Faltantes de Caja'); ?>

                <div class="container-fluid p-3">

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
                </div>
            </div>
    </body>

    </html>
<?php
}

function mostrarDetalleFaltanteCaja($conn, $id)
{
    global $usuario;
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
        <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
        <link rel="stylesheet" href="css/ver_faltante_caja.css?v=<?php echo mt_rand(1, 10000); ?>">
    </head>

    <body>
        <?php echo renderMenuLateral($cargoOperario); ?>

        <div class="main-container">
            <div class="sub-container">
                <?php echo renderHeader($usuario, 'Detalle Faltante de Caja #' . $id); ?>

                <div class="container-fluid p-3">

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
                </div>
            </div>
    </body>

    </html>
<?php
}
?>