<?php
//productos_get_datos.php
require_once '../../../includes/conexion.php';

header('Content-Type: application/json');

try {
    $membresia = isset($_POST['membresia']) ? trim($_POST['membresia']) : '';
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    
    if (empty($membresia)) {
        throw new Exception('Membresía no especificada');
    }
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Obtener información del cliente
    $sqlCliente = "SELECT nombre, apellido, nombre_sucursal, puntos_iniciales, fecha_registro
                   FROM clientesclub 
                   WHERE membresia = :membresia 
                   LIMIT 1";
    $stmtCliente = $conn->prepare($sqlCliente);
    $stmtCliente->execute([':membresia' => $membresia]);
    $cliente = $stmtCliente->fetch();
    
    if (!$cliente) {
        throw new Exception('Cliente no encontrado');
    }
    
    $puntosIniciales = floatval($cliente['puntos_iniciales']);
    
    // Contar total de registros (excluyendo grupos 25 y 11)
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM VentasGlobalesAccessCSV
                 LEFT JOIN DBBatidos ON VentasGlobalesAccessCSV.CodProducto = DBBatidos.CodBatido
                 WHERE VentasGlobalesAccessCSV.CodCliente = :membresia
                 AND (DBBatidos.CodGrupo IS NULL OR (DBBatidos.CodGrupo != 25 AND DBBatidos.CodGrupo != 11))";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute([':membresia' => $membresia]);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Calcular el total de puntos de TODO el historial (excluyendo grupos 25 y 11)
    $sqlTotalPuntos = "SELECT SUM(
                            CASE 
                                WHEN VentasGlobalesAccessCSV.Anulado = 0 THEN (VentasGlobalesAccessCSV.Cantidad * VentasGlobalesAccessCSV.Puntos)
                                ELSE 0 
                            END
                        ) as total_puntos
                       FROM VentasGlobalesAccessCSV
                       LEFT JOIN DBBatidos ON VentasGlobalesAccessCSV.CodProducto = DBBatidos.CodBatido
                       WHERE VentasGlobalesAccessCSV.CodCliente = :membresia
                       AND (DBBatidos.CodGrupo IS NULL OR (DBBatidos.CodGrupo != 25 AND DBBatidos.CodGrupo != 11))";
    $stmtTotalPuntos = $conn->prepare($sqlTotalPuntos);
    $stmtTotalPuntos->execute([':membresia' => $membresia]);
    $totalPuntosHistorial = floatval($stmtTotalPuntos->fetch()['total_puntos']) + $puntosIniciales;
    
    // Calcular los puntos de las páginas anteriores
    $sqlPuntosAnteriores = "SELECT SUM(
                                CASE 
                                    WHEN Anulado = 0 THEN (Cantidad * Puntos)
                                    ELSE 0 
                                END
                            ) as puntos_anteriores
                           FROM (
                               SELECT VentasGlobalesAccessCSV.Cantidad, VentasGlobalesAccessCSV.Puntos, VentasGlobalesAccessCSV.Anulado
                               FROM VentasGlobalesAccessCSV
                               LEFT JOIN DBBatidos ON VentasGlobalesAccessCSV.CodProducto = DBBatidos.CodBatido
                               WHERE VentasGlobalesAccessCSV.CodCliente = :membresia
                               AND (DBBatidos.CodGrupo IS NULL OR (DBBatidos.CodGrupo != 25 AND DBBatidos.CodGrupo != 11))
                               ORDER BY VentasGlobalesAccessCSV.Fecha DESC, VentasGlobalesAccessCSV.Hora DESC
                               LIMIT :offset
                           ) as subquery";
    $stmtPuntosAnteriores = $conn->prepare($sqlPuntosAnteriores);
    $stmtPuntosAnteriores->bindValue(':membresia', $membresia);
    $stmtPuntosAnteriores->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtPuntosAnteriores->execute();
    $puntosAnteriores = floatval($stmtPuntosAnteriores->fetch()['puntos_anteriores']);
    
    // Obtener datos de ventas para la página actual (excluyendo grupos 25 y 11)
    $sql = "SELECT 
                VentasGlobalesAccessCSV.Sucursal_Nombre,
                VentasGlobalesAccessCSV.CodPedido,
                VentasGlobalesAccessCSV.Fecha,
                VentasGlobalesAccessCSV.Hora,
                VentasGlobalesAccessCSV.DBBatidos_Nombre,
                VentasGlobalesAccessCSV.Medida,
                VentasGlobalesAccessCSV.Cantidad,
                VentasGlobalesAccessCSV.Puntos,
                VentasGlobalesAccessCSV.Anulado,
                CASE 
                    WHEN VentasGlobalesAccessCSV.Anulado = 0 THEN (VentasGlobalesAccessCSV.Cantidad * VentasGlobalesAccessCSV.Puntos)
                    ELSE 0 
                END as PuntosTotales
            FROM VentasGlobalesAccessCSV
            LEFT JOIN DBBatidos ON VentasGlobalesAccessCSV.CodProducto = DBBatidos.CodBatido
            WHERE VentasGlobalesAccessCSV.CodCliente = :membresia
            AND (DBBatidos.CodGrupo IS NULL OR (DBBatidos.CodGrupo != 25 AND DBBatidos.CodGrupo != 11))
            ORDER BY VentasGlobalesAccessCSV.Fecha DESC, VentasGlobalesAccessCSV.Hora DESC
            LIMIT :offset, :limit";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':membresia', $membresia);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    $datos = $stmt->fetchAll();
    
    // Calcular puntos acumulados comenzando desde el total (incluyendo puntos iniciales) y restando hacia atrás
    $puntosAcumulados = $totalPuntosHistorial - $puntosAnteriores;
    
    foreach ($datos as &$row) {
        $row['PuntosAcumulados'] = round($puntosAcumulados, 2);
        $puntosAcumulados -= floatval($row['PuntosTotales']);
    }
    
    echo json_encode([
        'success' => true,
        'cliente' => $cliente,
        'datos' => $datos,
        'total_registros' => $totalRegistros,
        'debug' => [
            'puntos_iniciales' => $puntosIniciales,
            'total_puntos_historial' => $totalPuntosHistorial,
            'puntos_anteriores' => $puntosAnteriores,
            'puntos_inicio_pagina' => $totalPuntosHistorial - $puntosAnteriores
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>