<?php
// diagnosticar_tabla.php - Script temporal para verificar la estructura
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h3>Diagnóstico de la tabla sucursales</h3>";
    
    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'sucursales'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "<div style='color: red;'>La tabla 'sucursales' no existe</div>";
    } else {
        echo "<div style='color: green;'>La tabla 'sucursales' existe</div>";
        
        // Mostrar estructura de la tabla
        $stmt = $pdo->query("DESCRIBE sucursales");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Columnas de la tabla:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Mostrar algunos datos de ejemplo
        echo "<h4>Datos de ejemplo:</h4>";
        $stmt = $pdo->query("SELECT * FROM sucursales LIMIT 5");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($data)) {
            echo "No hay datos en la tabla";
        } else {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr>";
            foreach (array_keys($data[0]) as $key) {
                echo "<th>$key</th>";
            }
            echo "</tr>";
            foreach ($data as $row) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>$value</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}
?>