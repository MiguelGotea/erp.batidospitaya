<?php
// public_html/modulos/mantenimiento/ajax/equipos_dashboard_plan.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $equipo_id = $input['equipo_id'] ?? 0;
    
    // Obtener frecuencia de mantenimiento
    $equipo = $db->fetchOne("
        SELECT frecuencia_mantenimiento_meses FROM mtto_equipos WHERE id = :id
    ", ['id' => $equipo_id]);
    
    // Último mantenimiento
    $ultimoMtto = $db->fetchOne("
        SELECT DATE_FORMAT(fecha_realizada, '%d/%m/%Y') as fecha
        FROM mtto_equipos_mantenimientos
        WHERE equipo_id = :equipo_id 
            AND estado = 'Completado'
            AND fecha_realizada IS NOT NULL
        ORDER BY fecha_realizada DESC
        LIMIT 1
    ", ['equipo_id' => $equipo_id]);
    
    $proximoMantenimiento = 'Nunca ha tenido mantenimiento';
    $colorProximo = '#dc3545';
    $diasRestantes = 'N/A';
    $colorDias = '#999';
    
    if ($ultimoMtto) {
        $fechaUltimo = new DateTime(str_replace('/', '-', $ultimoMtto['fecha']));
        $fechaProxima = clone $fechaUltimo;
        $fechaProxima->modify('+' . $equipo['frecuencia_mantenimiento_meses'] . ' months');
        
        $hoy = new DateTime();
        $diferencia = $hoy->diff($fechaProxima);
        $diasDiferencia = $diferencia->days * ($diferencia->invert ? -1 : 1);
        
        $proximoMantenimiento = $fechaProxima->format('d/m/Y');
        
        if ($diasDiferencia < 0) {
            $colorProximo = '#dc3545';
            $diasRestantes = abs($diasDiferencia) . ' días retrasado';
            $colorDias = '#dc3545';
        } elseif ($diasDiferencia <= 30) {
            $colorProximo = '#ffc107';
            $diasRestantes = $diasDiferencia . ' días';
            $colorDias = '#ffc107';
        } else {
            $colorProximo = '#28a745';
            $diasRestantes = $diasDiferencia . ' días';
            $colorDias = '#28a745';
        }
    }
    
    echo json_encode([
        'success' => true,
        'plan' => [
            'ultimo_mantenimiento' => $ultimoMtto['fecha'] ?? null,
            'proximo_mantenimiento' => $proximoMantenimiento,
            'color_proximo' => $colorProximo,
            'dias_restantes' => $diasRestantes,
            'color_dias' => $colorDias
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>