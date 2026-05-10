<?php
// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

verificarAccesoCargo([16, 21]);

header('Content-Type: application/json');

function tieneHorariosPendientesAnteriores() {
    global $conn;
    
    $semanaActual = obtenerSemanaActual();
    if (!$semanaActual) return false;
    
    $sql = "
        SELECT COUNT(*) as pendientes
        FROM (
            SELECT s.codigo, ss.numero_semana
            FROM sucursales s
            CROSS JOIN SemanasSistema ss
            LEFT JOIN HorariosSemanales hs ON s.codigo = hs.cod_sucursal AND ss.id = hs.id_semana_sistema
            LEFT JOIN HorariosSemanalesOperaciones hso ON s.codigo = hso.cod_sucursal AND ss.id = hso.id_semana_sistema
            WHERE s.activa = 1
            AND ss.numero_semana < ?
            AND hs.cod_operario IS NOT NULL
            GROUP BY s.codigo, ss.numero_semana
            HAVING COUNT(DISTINCT CASE WHEN hso.confirmado = 1 THEN hso.cod_operario END) < COUNT(DISTINCT hs.cod_operario)
               OR MAX(hs.fecha_actualizacion) > COALESCE(MAX(hso.fecha_confirmacion), '2000-01-01')
        ) as pendientes
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$semanaActual['numero_semana']]);
    $result = $stmt->fetch();
    
    return $result['pendientes'] > 0;
}

echo json_encode([
    'tiene_pendientes_anteriores' => tieneHorariosPendientesAnteriores()
]);
?>