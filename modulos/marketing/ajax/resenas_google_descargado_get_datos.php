<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('resenas_google_descargado', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para ver estos datos.']);
    exit;
}

try {
    // Consulta para obtener las reseñas unidas con la tabla de sucursales
    // Se usa cod_googlebusiness para enlazar con locationId
    $sql = "SELECT 
                r.locationId,
                r.reviewerName,
                r.starRating,
                r.comment,
                r.createTime,
                s.nombre AS SucursalNombre
            FROM ResenasGoogle r
            LEFT JOIN sucursales s ON r.locationId = s.cod_googlebusiness
            ORDER BY r.createTime DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $resenas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar datos para el formato de la tabla
    foreach ($resenas as &$r) {
        // Convertir starRating a número (ONE -> 1, TWO -> 2, etc.)
        $ratingMap = [
            'ONE' => 1,
            'TWO' => 2,
            'THREE' => 3,
            'FOUR' => 4,
            'FIVE' => 5
        ];
        $r['starRatingNum'] = isset($ratingMap[$r['starRating']]) ? $ratingMap[$r['starRating']] : 0;
        
        // Formatear fecha (solo fecha si es posible)
        // El formato de createTime suele ser ISO 8601 (2024-03-09T12:00:00Z)
        if (!empty($r['createTime'])) {
            $date = new DateTime($r['createTime']);
            $r['fechaFormateada'] = $date->format('d-m-Y');
        } else {
            $r['fechaFormateada'] = 'N/A';
        }
        
        // Asegurar que el nombre de la sucursal no sea nulo
        if (empty($r['SucursalNombre'])) {
            $r['SucursalNombre'] = 'Desconocida (' . $r['locationId'] . ')';
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $resenas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
?>
