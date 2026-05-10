<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once '../includes/funciones_compras.php';
require_once '../../../core/helpers/config.php';


header('Content-Type: application/json');

try {
    // Verificar que sea cargo 9 (Compras) o admin
    if (!puedeCompletarSolicitudes()) {
        throw new Exception('No tiene permisos para agregar notas');
    }
    
    $productoId = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $nota = isset($_POST['nota']) ? trim($_POST['nota']) : '';
    
    if ($productoId <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    if (empty($nota)) {
        throw new Exception('La nota no puede estar vacía');
    }
    
    // Verificar que el producto existe
    $stmt = $conn->prepare("SELECT * FROM solicitudes_cotizacion_productos WHERE id = ?");
    $stmt->execute([$productoId]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }
    
    // Actualizar la nota
    $stmt = $conn->prepare("
        UPDATE solicitudes_cotizacion_productos 
        SET notas_compras = ?, 
            fecha_notas_compras = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$nota, $productoId]);
    
    // Registrar en el historial
    $usuario = obtenerUsuarioActual();
    $usuarioNombre = trim($usuario['Nombre'] . ' ' . $usuario['Apellido']);
    
    $stmtHistorial = $conn->prepare("
        INSERT INTO solicitudes_cotizacion_historial 
        (solicitud_id, usuario_id, usuario_nombre, accion, detalles) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $detalles = json_encode([
        'producto_id' => $productoId,
        'producto_descripcion' => $producto['producto_descripcion'],
        'nota_agregada' => $nota
    ]);
    
    $stmtHistorial->execute([
        $producto['solicitud_id'],
        $_SESSION['usuario_id'],
        $usuarioNombre,
        'nota_producto_agregada',
        $detalles
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Nota guardada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>