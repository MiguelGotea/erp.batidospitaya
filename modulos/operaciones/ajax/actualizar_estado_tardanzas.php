<?php
require_once '../../includes/auth.php';
/**
 * Script para actualizar automáticamente el estado de tardanzas pendientes a "No Válido"
 * después de 3 días de la fecha límite
 * 
 * Este script debe ejecutarse via cron job diariamente
 * Ejemplo de cron: 0 2 * * * /usr/bin/php /ruta/al/archivo/actualizar_estado_tardanzas.php
 */

// Solo permitir ejecución via CLI
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse via línea de comandos');
}

try {
    echo "Iniciando actualización automática de estado de tardanzas...\n";
    
    // Calcular fecha límite (día 3 del mes actual)
    $fechaLimite = new DateTime('first day of this month');
    $fechaLimite->modify('+2 days'); // Día 3 del mes
    
    // Calcular fecha de corte (3 días después de la fecha límite)
    $fechaCorte = clone $fechaLimite;
    $fechaCorte->modify('+3 days');
    
    $hoy = new DateTime();
    
    echo "Fecha límite: " . $fechaLimite->format('Y-m-d') . "\n";
    echo "Fecha de corte: " . $fechaCorte->format('Y-m-d') . "\n";
    echo "Hoy: " . $hoy->format('Y-m-d') . "\n";
    
    // Solo ejecutar si hoy es después de la fecha de corte
    if ($hoy < $fechaCorte) {
        echo "Aún no es momento de actualizar los estados (fecha de corte: {$fechaCorte->format('Y-m-d')})\n";
        exit(0);
    }
    
    // Calcular periodo del mes anterior
    $mesAnterior = new DateTime('first day of last month');
    $inicioPeriodo = $mesAnterior->format('Y-m-01');
    $finPeriodo = $mesAnterior->format('Y-m-t');
    
    echo "Periodo a procesar: {$inicioPeriodo} a {$finPeriodo}\n";
    
    // Obtener tardanzas pendientes del mes anterior
    $sql = "
        SELECT id, cod_operario, fecha_tardanza, sucursal_nombre
        FROM TardanzasManuales 
        WHERE fecha_tardanza BETWEEN ? AND ?
        AND estado = 'Pendiente'
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$inicioPeriodo, $finPeriodo]);
    $tardanzasPendientes = $stmt->fetchAll();
    
    $totalActualizadas = 0;
    
    foreach ($tardanzasPendientes as $tardanza) {
        // Actualizar estado a "No Válido"
        $sqlUpdate = "UPDATE TardanzasManuales SET estado = 'No Válido' WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([$tardanza['id']]);
        
        $totalActualizadas++;
        
        echo "Actualizada tardanza ID: {$tardanza['id']} - {$tardanza['sucursal_nombre']} - {$tardanza['fecha_tardanza']}\n";
        
        // Registrar en log de sistema
        registrarLogSistema(
            'AUTO_TARDANZAS',
            "Tardanza automáticamente marcada como No Válido",
            [
                'tardanza_id' => $tardanza['id'],
                'cod_operario' => $tardanza['cod_operario'],
                'fecha_tardanza' => $tardanza['fecha_tardanza'],
                'fecha_actualizacion' => $hoy->format('Y-m-d H:i:s')
            ]
        );
    }
    
    echo "Proceso completado. Total de tardanzas actualizadas: {$totalActualizadas}\n";
    
    // Enviar notificación por email (opcional)
    if ($totalActualizadas > 0) {
        enviarNotificacionActualizacion($totalActualizadas, $inicioPeriodo, $finPeriodo);
    }
    
} catch (Exception $e) {
    echo "Error en el proceso: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Registrar en log del sistema
 */
function registrarLogSistema($tipo, $mensaje, $datos = []) {
    global $conn;
    
    $sql = "INSERT INTO logs_sistema (tipo, mensaje, datos, fecha) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$tipo, $mensaje, json_encode($datos)]);
}

/**
 * Enviar notificación por email (opcional)
 */
function enviarNotificacionActualizacion($total, $inicioPeriodo, $finPeriodo) {
    $asunto = "Actualización Automática de Tardanzas - " . date('d/m/Y');
    $mensaje = "
        Se han actualizado automáticamente {$total} tardanzas pendientes del periodo 
        {$inicioPeriodo} al {$finPeriodo} a estado 'No Válido'.
        
        Este proceso se ejecuta automáticamente 3 días después de la fecha límite 
        (día 3 de cada mes) para las tardanzas no justificadas del mes anterior.
        
        Fecha de ejecución: " . date('d/m/Y H:i:s') . "
    ";
    
    // Aquí puedes implementar el envío de email
    // mail('destinatario@empresa.com', $asunto, $mensaje);
    
    echo "Notificación preparada para envío\n";
}
?>