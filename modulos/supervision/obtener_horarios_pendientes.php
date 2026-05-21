<?php
// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

verificarAccesoCargo([21, 49]);

header('Content-Type: application/json');

function obtenerHorariosPendientes() {
    global $conn;
    
    $semanaActual = obtenerSemanaActual();
    if (!$semanaActual) {
        return ['success' => false, 'message' => 'No se pudo obtener la semana actual'];
    }
    
    // Obtener todas las semanas con horarios pendientes (desde la semana actual hacia atrás)
    $sql = "
        SELECT 
            ss.id as semana_id,
            ss.numero_semana,
            ss.fecha_inicio,
            ss.fecha_fin,
            s.codigo as sucursal_codigo,
            s.nombre as sucursal_nombre,
            COUNT(DISTINCT hs.cod_operario) as total_operarios,
            COUNT(DISTINCT CASE WHEN hso.confirmado = 1 THEN hso.cod_operario END) as operarios_confirmados,
            MAX(hs.fecha_actualizacion) as ultima_actualizacion_lider,
            MAX(hso.fecha_actualizacion) as ultima_confirmacion_operaciones,
            MAX(hso.fecha_creacion) as fecha_creacion_operaciones,
            CASE 
                WHEN (
                    (MAX(hso.fecha_actualizacion) IS NULL AND MAX(hs.fecha_actualizacion) > MAX(hso.fecha_creacion))
                    OR 
                    (MAX(hso.fecha_actualizacion) IS NOT NULL AND MAX(hs.fecha_actualizacion) > MAX(hso.fecha_actualizacion))
                ) THEN 1
                ELSE 0
            END as tiene_cambios_pendientes
        FROM sucursales s
        CROSS JOIN SemanasSistema ss
        INNER JOIN HorariosSemanales hs ON s.codigo = hs.cod_sucursal AND ss.id = hs.id_semana_sistema
        LEFT JOIN HorariosSemanalesOperaciones hso ON s.codigo = hso.cod_sucursal AND ss.id = hso.id_semana_sistema AND hs.cod_operario = hso.cod_operario
        WHERE s.activa = 1
        AND ss.numero_semana <= ?
        AND ss.numero_semana >= (? - 4)
        GROUP BY ss.id, ss.numero_semana, ss.fecha_inicio, ss.fecha_fin, s.codigo, s.nombre
        HAVING 
            -- Horarios no confirmados completamente
            COUNT(DISTINCT CASE WHEN hso.confirmado = 1 THEN hso.cod_operario END) < COUNT(DISTINCT hs.cod_operario)
            -- O horarios con cambios pendientes después de confirmación (nueva lógica)
            OR tiene_cambios_pendientes = 1
        ORDER BY ss.numero_semana DESC, s.nombre ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$semanaActual['numero_semana'], $semanaActual['numero_semana']]);
    $resultados = $stmt->fetchAll();
    
    $horariosPendientes = [];
    $totalPendientes = 0;
    
    foreach ($resultados as $fila) {
        $horariosPendientes[] = $fila;
        $totalPendientes++;
    }
    
    return [
        'success' => true,
        'total_pendientes' => $totalPendientes,
        'horarios_pendientes' => $horariosPendientes,
        'semana_actual' => $semanaActual['numero_semana']
    ];
}

// Ejecutar y devolver resultados
try {
    $resultado = obtenerHorariosPendientes();
    echo json_encode($resultado);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>