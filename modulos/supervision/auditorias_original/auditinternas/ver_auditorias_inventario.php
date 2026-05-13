<?php
// Incluir configuración y verificar autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../../core/layout/menu_lateral.php';
require_once '../../../../core/layout/header_universal.php';
require_once '../../../../core/permissions/permissions.php';

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

// Verificar que se haya proporcionado un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: auditorias_consolidadas.php');
    exit;
}

$auditoria_id = intval($_GET['id']);

try {
    // $db = conectarDB();
    $db = $conn;
    
    // Obtener información principal de la auditoría
    $stmt = $db->prepare("
        SELECT 
            ai.*,
            CONCAT(u.Nombre, ' ', u.Apellido) AS auditor_nombre
        FROM auditoria_inventario ai
        LEFT JOIN Operarios u ON ai.auditor_id = u.CodOperario
        WHERE ai.id = ?
    ");
    $stmt->execute([$auditoria_id]);
    $auditoria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$auditoria) {
        header('Location: auditorias_consolidadas.php');
        exit;
    }
    
    // Obtener los detalles de los productos
    $stmt = $db->prepare("
        SELECT * FROM auditoria_inventario_detalle 
        WHERE auditoria_id = ?
        ORDER BY id
    ");
    $stmt->execute([$auditoria_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener los operarios relacionados con sus montos
    $stmt = $db->prepare("
        SELECT * FROM auditoria_inventario_operarios 
        WHERE auditoria_id = ? AND monto != 0
        ORDER BY monto DESC
    ");
    $stmt->execute([$auditoria_id]);
    $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular total faltante (valor absoluto)
    $total_faltante = abs($auditoria['total_faltante']);
    
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Formatear fecha
function formatFechaHora($fecha_hora) {
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $fecha = new DateTime($fecha_hora);
    $fecha->sub(new DateInterval('PT6H')); // Ajustar a hora de Nicaragua
    
    $dia = $fecha->format('d');
    $mes = $meses[(int)$fecha->format('m') - 1];
    $anio = $fecha->format('y');
    $hora = $fecha->format('H:i');
    
    return "$dia-$mes-$anio $hora";
}

// Obtener color según el nombre de la categoría (soporta nombres viejos y nuevos)
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
    <title>Detalle Auditoría Inventario</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/ver_auditorias_inventario.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($usuario['CodNivelesCargos']); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Detalle Auditoría Inventario #' . $auditoria_id); ?>
            
            <div class="container-fluid p-3">
        
        <h1>Detalle de Auditoría de Inventario #<?php echo $auditoria_id; ?></h1>
        
        <div class="auditoria-info">
            <div class="info-row">
                <div class="info-label">Fecha y Hora:</div>
                <div class="info-value"><?php echo formatFechaHora($auditoria['fecha_hora_regsys']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Sucursal:</div>
                <div class="info-value"><?php echo htmlspecialchars($auditoria['sucursal']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Auditor:</div>
                <div class="info-value">
                    <?php 
                        echo $auditoria['auditor_nombre'] ? htmlspecialchars($auditoria['auditor_nombre']) : 'No registrado';
                    ?>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Total Faltante:</div>
                <div class="info-value">C$ <?php echo number_format($total_faltante, 2); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Comentarios:</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($auditoria['comentarios'])); ?></div>
            </div>
        </div>
        
        <h2>Productos con diferencias</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Inv. Sistema</th>
                        <th>Inv. Físico</th>
                        <th>Diferencia</th>
                        <th>Costo Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($detalle['producto']); ?></td>
                        <td><?php echo $detalle['inventario_sistema']; ?></td>
                        <td><?php echo $detalle['inventario_fisico']; ?></td>
                        <td class="<?php echo ($detalle['diferencia'] < 0) ? 'diferencia-negativa' : 'diferencia-positiva'; ?>">
                            <?php echo $detalle['diferencia']; ?>
                        </td>
                        <td>C$ <?php echo number_format($detalle['costo_unitario'], 2); ?></td>
                        <td>C$ <?php echo number_format($detalle['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5">Total Faltante:</td>
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
        
        <?php if (!empty($auditoria['foto_path']) || !empty($auditoria['foto_path_2'])): ?>
        <h2>Fotos de evidencia</h2>
        <div class="photo-container">
            <?php if (!empty($auditoria['foto_path'])): ?>
            <div class="photo-item">
                <img src="<?php echo htmlspecialchars($auditoria['foto_path']); ?>" alt="Foto de evidencia 1">
                <div class="photo-caption">Foto 1 - Insumos Importantes</div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($auditoria['foto_path_2'])): ?>
            <div class="photo-item">
                <img src="<?php echo htmlspecialchars($auditoria['foto_path_2']); ?>" alt="Foto de evidencia 2">
                <div class="photo-caption">Foto 2 - Mostrador</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
