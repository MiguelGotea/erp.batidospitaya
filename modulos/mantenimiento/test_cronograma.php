<?php
// test_cronograma.php - Archivo para diagnosticar problemas
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnóstico del Sistema de Programación</h2>";

// Test 1: Conexión a base de datos
echo "<h3>1. Probando conexión a base de datos...</h3>";
try {
    require_once __DIR__ . '/config/database.php';
    echo "✅ Conexión exitosa<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: Tabla FechasSistema
echo "<h3>2. Probando tabla FechasSistema...</h3>";
try {
    $sql = "SELECT COUNT(*) as total FROM FechasSistema WHERE numero_semana = 518";
    $result = $db->fetchOne($sql);
    echo "✅ Registros encontrados para semana 518: " . $result['total'] . "<br>";
    
    if ($result['total'] == 0) {
        echo "⚠️ ADVERTENCIA: No hay fechas para la semana 518<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Obtener fechas de la semana
echo "<h3>3. Obteniendo fechas de la semana 518...</h3>";
try {
    $sql = "
        SELECT DATE(fecha) as fecha, 
               DATE_FORMAT(fecha, '%d/%m') as fecha_formato,
               DAYOFWEEK(fecha) as dia_semana
        FROM FechasSistema 
        WHERE numero_semana = 518
        AND DAYOFWEEK(fecha) BETWEEN 2 AND 7
        ORDER BY fecha
        LIMIT 6
    ";
    
    $fechas = $db->fetchAll($sql, []);
    
    if (empty($fechas)) {
        echo "❌ No se encontraron fechas<br>";
    } else {
        echo "✅ Fechas encontradas: " . count($fechas) . "<br>";
        echo "<pre>";
        print_r($fechas);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 4: Equipos de trabajo
echo "<h3>4. Probando equipos de trabajo...</h3>";
try {
    $sql = "
        SELECT GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR '|') as tipos_usuario,
               COUNT(*) as num_tickets
        FROM mtto_tickets_colaboradores tc
        INNER JOIN mtto_tickets t ON tc.ticket_id = t.id
        WHERE t.tipo_formulario = 'mantenimiento_general'
        AND tc.tipo_usuario IS NOT NULL
        GROUP BY tc.ticket_id
        LIMIT 10
    ";
    
    $combinaciones = $db->fetchAll($sql);
    
    if (empty($combinaciones)) {
        echo "⚠️ No hay equipos de trabajo registrados<br>";
    } else {
        echo "✅ Combinaciones encontradas: " . count($combinaciones) . "<br>";
        echo "<pre>";
        print_r($combinaciones);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 5: Tickets programados
echo "<h3>5. Probando tickets programados...</h3>";
try {
    $sql = "
        SELECT COUNT(*) as total
        FROM mtto_tickets
        WHERE DATE(fecha_inicio) IS NOT NULL
        AND DATE(fecha_final) IS NOT NULL
    ";
    
    $result = $db->fetchOne($sql);
    echo "✅ Tickets programados: " . $result['total'] . "<br>";
    
    if ($result['total'] == 0) {
        echo "⚠️ ADVERTENCIA: No hay tickets programados<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 6: Tickets sin programar
echo "<h3>6. Probando tickets sin programar...</h3>";
try {
    $sql = "
        SELECT COUNT(*) as total
        FROM mtto_tickets
        WHERE (DATE(fecha_inicio) IS NULL OR DATE(fecha_final) IS NULL)
        AND status != 'finalizado'
    ";
    
    $result = $db->fetchOne($sql);
    echo "✅ Tickets sin programar: " . $result['total'] . "<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 7: Simular respuesta JSON del endpoint
echo "<h3>7. Simulando respuesta del endpoint...</h3>";
try {
    ob_start();
    include __DIR__ . '/ajax/agenda_get_cronograma.php';
    $output = ob_get_clean();
    
    echo "Respuesta del servidor:<br>";
    echo "<textarea style='width:100%;height:200px;'>" . htmlspecialchars($output) . "</textarea><br>";
    
    $decoded = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido<br>";
        echo "Equipos: " . count($decoded['equipos']) . "<br>";
        echo "Fechas: " . count($decoded['fechas']) . "<br>";
    } else {
        echo "❌ JSON inválido: " . json_last_error_msg() . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>✅ Diagnóstico completado</h3>";
?>