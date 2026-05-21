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
verificarAccesoCargo([8, 11, 16, 21, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 11, 16, 21, 49])) {
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
    $db = $conn;
    
    // Obtener información principal del faltante por daños
    $stmt = $db->prepare("
        SELECT 
            fd.*,
            CONCAT(u.Nombre, ' ', u.Apellido) AS registrador_nombre
        FROM faltante_danos fd
        LEFT JOIN Operarios u ON fd.registrador_id = u.CodOperario
        WHERE fd.id = ?
    ");
    $stmt->execute([$faltante_id]);
    $faltante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$faltante) {
        header('Location: auditorias_consolidadas.php');
        exit;
    }
    
    // Obtener los operarios relacionados con sus montos (solo los que tienen monto != 0)
    $stmt = $db->prepare("
        SELECT * FROM faltante_danos_operarios 
        WHERE faltante_id = ? AND monto != 0
        ORDER BY monto DESC
    ");
    $stmt->execute([$faltante_id]);
    $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Detalle Faltante Daños</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/ver_faltante_danos.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($usuario['CodNivelesCargos']); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Detalle Faltante Daños'); ?>
            
            <div class="container-fluid p-3">
        
        <div class="faltante-info">
            <div class="info-row">
                <div class="info-label">Fecha y Hora:</div>
                <div class="info-value"><?php echo formatFechaHora($faltante['fecha_hora_regsys']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Sucursal:</div>
                <div class="info-value"><?php echo htmlspecialchars($faltante['sucursal_nombre']); ?></div>
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
                <div class="info-label">Producto Dañado:</div>
                <div class="info-value"><?php echo htmlspecialchars($faltante['producto_danado']); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Valor Faltante:</div>
                <div class="info-value monto-negativo">C$ <?php echo number_format($faltante['valor_faltante'], 2); ?></div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Comentarios:</div>
                <div class="info-value"><?php echo nl2br(htmlspecialchars($faltante['comentarios'])); ?></div>
            </div>
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
                        <td class="monto-negativo">C$ <?php echo number_format(abs($operario['monto']), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
