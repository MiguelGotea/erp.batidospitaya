<?php
/**
 * Obtener progreso de tarea o reunión
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Obtener el item
    $sql = "SELECT tipo, progreso, estado FROM gestion_tareas_reuniones_items WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item no encontrado');
    }

    // Si ya está finalizado, el progreso es 100%
    if ($item['estado'] === 'finalizado') {
        echo json_encode([
            'success' => true,
            'progreso' => 100,
            'label' => '100%'
        ]);
        exit();
    }

    $progreso = floatval($item['progreso']);
    $label = '';

    if ($item['tipo'] == 'tarea') {
        // Contar subtareas
        $sqlSubtareas = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizadas
                         FROM gestion_tareas_reuniones_items
                         WHERE id_padre = :id AND tipo = 'subtarea'";
        $stmtSub = $conn->prepare($sqlSubtareas);
        $stmtSub->execute([':id' => $id]);
        $subtareas = $stmtSub->fetch(PDO::FETCH_ASSOC);

        if ($subtareas['total'] > 0) {
            $progreso = ($subtareas['finalizadas'] / $subtareas['total']) * 100;
            $label = $subtareas['finalizadas'] . ' de ' . $subtareas['total'] . ' subtareas';
        } else {
            $progreso = $item['progreso'] == 100 ? 100 : 0; // Si no hay subtareas, depende de si el item principal está finalizado
            $label = 'Sin subtareas';
        }
    } else if ($item['tipo'] == 'reunion') {
        // Contar participantes
        $sqlPart = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN confirmacion != 'pendiente' THEN 1 ELSE 0 END) as confirmados
                    FROM gestion_tareas_reuniones_participantes
                    WHERE id_item = :id";
        $stmtPart = $conn->prepare($sqlPart);
        $stmtPart->execute([':id' => $id]);
        $participantes = $stmtPart->fetch(PDO::FETCH_ASSOC);

        if ($participantes['total'] > 0) {
            $progreso = ($participantes['confirmados'] / $participantes['total']) * 100;
        } else {
            $progreso = 0;
        }
        $label = $participantes['confirmados'] . ' de ' . $participantes['total'] . ' confirmados';
    }

    echo json_encode([
        'success' => true,
        'progreso' => $progreso,
        'label' => $label
    ]);

} catch (Exception $e) {
    error_log("Error en get_progreso: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>