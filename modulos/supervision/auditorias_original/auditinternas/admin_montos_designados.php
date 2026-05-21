<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
// Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// Obtener conexión a la base de datos
// $conn = conectarDB(); // Comentado por migración al core
$conn = $conn;

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([8, 11, 16, 21, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 11, 21, 16, 49])) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sucursalId = $_POST['sucursal_id'];
    $monto = intval($_POST['monto']);
    
    // Verificar si ya existe un registro
    $stmtCheck = $conn->prepare("SELECT id FROM caja_chica_sucursales WHERE sucursal_id = ?");
    $stmtCheck->execute([$sucursalId]);
    
    if ($stmtCheck->rowCount() > 0) {
        // Actualizar existente
        $stmt = $conn->prepare("UPDATE caja_chica_sucursales SET monto_designado = ?, fecha_modificacion = CONVERT_TZ(NOW(), 'SYSTEM', '-06:00') WHERE sucursal_id = ?");
        $stmt->execute([$monto, $sucursalId]);
    } else {
        // Insertar nuevo
        $stmt = $conn->prepare("INSERT INTO caja_chica_sucursales (sucursal_id, monto_designado, fecha_modificacion) VALUES (?, ?, CONVERT_TZ(NOW(), 'SYSTEM', '-06:00'))");
        $stmt->execute([$sucursalId, $monto]);
    }
    
    // Obtener la fecha actualizada
    $stmtFecha = $conn->prepare("SELECT fecha_modificacion FROM caja_chica_sucursales WHERE sucursal_id = ?");
    $stmtFecha->execute([$sucursalId]);
    $fechaActualizada = $stmtFecha->fetchColumn();
    
    header("Location: admin_montos_designados.php?success=1&sucursal_id=$sucursalId&fecha_actualizada=".urlencode($fechaActualizada));
    exit();
}

// Obtener datos de sucursales
$stmtSucursales = $conn->query("
    SELECT s.codigo, s.nombre, cs.monto_designado, cs.fecha_modificacion 
    FROM sucursales s
    LEFT JOIN caja_chica_sucursales cs ON s.codigo = cs.sucursal_id AND cs.activo = 1
    WHERE s.activa = 1 AND s.codigo NOT IN (0, 14)
    ORDER BY s.nombre
");
$sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);

// Actualizar fecha si viene de un guardado reciente
if (isset($_GET['success'])) {
    $sucursalId = $_GET['sucursal_id'] ?? null;
    $fechaActualizada = $_GET['fecha_actualizada'] ?? null;
    
    if ($sucursalId && $fechaActualizada) {
        foreach ($sucursales as &$sucursal) {
            if ($sucursal['id'] == $sucursalId) {
                $sucursal['fecha_modificacion'] = urldecode($fechaActualizada);
                break;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Montos Designados</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="css/admin_montos_designados.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <div class="header">
        <img src="/core/assets/img/Logo.svg" alt="Logo">
    </div>
    
    <div class="container">
        <h2>Administrar Montos Designados por Sucursal</h2>
        
        <?php if (isset($_GET['success'])): ?>
        <div class="success-message" id="successMessage">
            Los cambios se han guardado correctamente.
        </div>
        <script>
            document.getElementById('successMessage').style.display = 'block';
            setTimeout(function() {
                document.getElementById('successMessage').style.display = 'none';
                if (window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('success');
                    url.searchParams.delete('sucursal_id');
                    url.searchParams.delete('fecha_actualizada');
                    window.history.replaceState(null, null, url);
                }
            }, 3000);
        </script>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Sucursal</th>
                    <th>Monto Designado</th>
                    <th>Última Modificación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sucursales as $sucursal): ?>
                <tr>
                    <td><?= htmlspecialchars($sucursal['nombre']) ?></td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="sucursal_id" value="<?= $sucursal['codigo'] ?>">
                            <input type="number" name="monto" min="0" 
                                   value="<?= isset($sucursal['monto_designado']) ? intval($sucursal['monto_designado']) : 0 ?>" required>
                    </td>
                    <td class="fecha-modificacion">
                        <?= formatFechaEspanol($sucursal['fecha_modificacion'] ?? null) ?>
                    </td>
                    <td>
                            <button type="submit">Guardar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
