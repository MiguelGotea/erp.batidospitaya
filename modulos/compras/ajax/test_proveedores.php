<?php
// Guardar este archivo como: /public_html/modulos/compras/ajax/test_proveedores.php
// Luego acceder a: http://tudominio.com/modulos/compras/ajax/test_proveedores.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Test de Diagnóstico - Sistema de Proveedores</h2>";

// Test 1: Verificar ruta de conexión
echo "<h3>1. Verificando archivo de conexión...</h3>";
$rutaConexion = '../../../core/database/conexion.php';
if (file_exists($rutaConexion)) {
    echo "✅ Archivo de conexión encontrado<br>";
    require_once $rutaConexion;
} else {
    echo "❌ ERROR: No se encuentra el archivo de conexión en: " . realpath('.') . "/$rutaConexion<br>";
    exit;
}

// Test 2: Verificar conexión a BD
echo "<h3>2. Verificando conexión a base de datos...</h3>";
if (isset($conn)) {
    echo "✅ Variable \$conn existe<br>";
    try {
        $conn->query("SELECT 1");
        echo "✅ Conexión a base de datos activa<br>";
    } catch (PDOException $e) {
        echo "❌ ERROR en conexión: " . $e->getMessage() . "<br>";
        exit;
    }
} else {
    echo "❌ ERROR: Variable \$conn no está definida<br>";
    exit;
}

// Test 3: Verificar que exista la tabla proveedores
echo "<h3>3. Verificando tabla 'proveedores'...</h3>";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'proveedores'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'proveedores' existe<br>";
        
        // Contar registros
        $stmtCount = $conn->query("SELECT COUNT(*) as total FROM proveedores");
        $total = $stmtCount->fetch()['total'];
        echo "📊 Total de proveedores en BD: $total<br>";
    } else {
        echo "❌ ERROR: Tabla 'proveedores' NO existe<br>";
        echo "⚠️ Necesitas ejecutar el script SQL primero<br>";
        exit;
    }
} catch (PDOException $e) {
    echo "❌ ERROR al verificar tabla: " . $e->getMessage() . "<br>";
    exit;
}

// Test 4: Verificar tabla sucursales
echo "<h3>4. Verificando tabla 'sucursales'...</h3>";
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'sucursales'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'sucursales' existe<br>";
    } else {
        echo "❌ ERROR: Tabla 'sucursales' NO existe<br>";
        exit;
    }
} catch (PDOException $e) {
    echo "❌ ERROR al verificar tabla: " . $e->getMessage() . "<br>";
    exit;
}

// Test 5: Probar consulta completa
echo "<h3>5. Probando consulta de datos...</h3>";
try {
    $sql = "SELECT 
                p.id,
                p.nombre,
                p.ruc_nit,
                p.direccion,
                p.vigente,
                p.fecha_registro,
                s.nombre as nombre_sucursal
            FROM proveedores p
            LEFT JOIN sucursales s ON p.comprasucursal = s.codigo
            ORDER BY p.fecha_registro DESC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $datos = $stmt->fetchAll();
    
    echo "✅ Consulta ejecutada correctamente<br>";
    echo "📊 Registros obtenidos: " . count($datos) . "<br>";
    
    if (count($datos) > 0) {
        echo "<h4>Muestra de datos:</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>RUC/NIT</th><th>Vigente</th><th>Sucursal</th></tr>";
        foreach ($datos as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['nombre']}</td>";
            echo "<td>{$row['ruc_nit']}</td>";
            echo "<td>" . ($row['vigente'] ? 'Sí' : 'No') . "</td>";
            echo "<td>{$row['nombre_sucursal']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "⚠️ No hay proveedores registrados aún<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ ERROR en consulta: " . $e->getMessage() . "<br>";
    exit;
}

// Test 6: Probar respuesta JSON
echo "<h3>6. Probando respuesta JSON...</h3>";
try {
    $response = [
        'success' => true,
        'datos' => $datos,
        'total_registros' => count($datos)
    ];
    
    $json = json_encode($response);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON generado correctamente<br>";
        echo "<details><summary>Ver JSON (click para expandir)</summary>";
        echo "<pre>" . htmlspecialchars($json) . "</pre>";
        echo "</details>";
    } else {
        echo "❌ ERROR al generar JSON: " . json_last_error_msg() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>✅ Diagnóstico Completado</h3>";
echo "<p>Si todos los tests pasaron, el problema podría estar en:</p>";
echo "<ul>";
echo "<li>Permisos de archivos</li>";
echo "<li>Ruta incorrecta en proveedores.js</li>";
echo "<li>Sesión del usuario</li>";
echo "</ul>";
?>