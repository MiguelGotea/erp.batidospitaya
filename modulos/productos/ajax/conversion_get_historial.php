<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
session_start();

header('Content-Type: application/json');

try {
    // Verificar sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('Sesión no válida');
    }
    
    $cargoOperario = $_SESSION['cargo_cod'];
    
    // Verificar permiso
    if (!tienePermiso('unidades_conversion_productos', 'vista', $cargoOperario)) {
        throw new Exception('No tiene permiso para ver esta herramienta');
    }
    
    $id_unidad = isset($_POST['id_unidad']) ? (int)$_POST['id_unidad'] : 0;
    
    if ($id_unidad <= 0) {
        throw new Exception('ID de unidad inválido');
    }
    
    // Obtener conversiones donde esta unidad es inicio o final
    // Usando parámetros posicionales (?) para evitar el error HY093
    $sql = "SELECT 
                c.id,
                c.cantidad,
                c.fecha_creacion,
                ui.nombre as unidad_inicio,
                uf.nombre as unidad_final,
                CONCAT(COALESCE(o.Nombre, ''), ' ', COALESCE(o.Apellido, '')) as usuario_creacion
            FROM conversion_unidad_producto c
            INNER JOIN unidad_producto ui ON c.id_unidad_producto_inicio = ui.id
            INNER JOIN unidad_producto uf ON c.id_unidad_producto_final = uf.id
            LEFT JOIN Operarios o ON c.usuario_creacion = o.CodOperario
            WHERE c.id_unidad_producto_inicio = ? 
               OR c.id_unidad_producto_final = ?
            ORDER BY c.fecha_creacion DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_unidad, $id_unidad]); // Pasar el mismo ID dos veces
    $conversiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Siempre devolver success: true, incluso si no hay conversiones
    echo json_encode([
        'success' => true,
        'conversiones' => $conversiones,
        'total' => count($conversiones),
        'id_unidad_consultada' => $id_unidad
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'conversiones' => []
    ]);
}