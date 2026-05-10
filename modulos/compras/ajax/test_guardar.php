<?php
echo $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
// Guardar como: /public_html/modulos/compras/ajax/test_guardar.php
// Acceder a: http://tudominio.com/modulos/compras/ajax/test_guardar.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Test de Guardado - Proveedores</h2>";


// Test 1: Verificar conexión
echo "<h3>1. Verificando conexión...</h3>";
if (isset($conn)) {
    echo "✅ Conexión OK<br>";
}
else {
    echo "❌ ERROR: No hay conexión<br>";
    exit;
}

// Test 2: Verificar autenticación
echo "<h3>2. Verificando autenticación...</h3>";
if (file_exists('../../../core/auth/auth.php')) {
require_once '../../../core/auth/auth.php';
    echo "✅ Archivo auth.php encontrado<br>";

    // Verificar si la función existe
    if (function_exists('obtenerUsuarioActual')) {
        echo "✅ Función obtenerUsuarioActual() existe<br>";

        try {
            $usuario = obtenerUsuarioActual();
            if ($usuario && isset($usuario['usuario_id'])) {
                echo "✅ Usuario autenticado: ID = " . $usuario['usuario_id'] . "<br>";
                echo "📊 Datos del usuario:<br>";
                echo "<pre>" . print_r($usuario, true) . "</pre>";
            }
            else {
                echo "⚠️ ADVERTENCIA: Usuario no autenticado o estructura incorrecta<br>";
                echo "<pre>" . print_r($usuario, true) . "</pre>";
            }
        }
        catch (Exception $e) {
            echo "❌ ERROR al obtener usuario: " . $e->getMessage() . "<br>";
        }
    }
    else {
        echo "❌ ERROR: Función obtenerUsuarioActual() no existe<br>";
    }
}
else {
    echo "❌ ERROR: Archivo auth.php no encontrado<br>";
}

// Test 3: Simular guardado
echo "<h3>3. Simulando guardado de proveedor...</h3>";

// Datos de prueba
$datosTest = [
    'nombre' => 'Proveedor de Prueba',
    'ruc_nit' => '123456789',
    'direccion' => 'Dirección de prueba',
    'comprasucursal' => null,
    'vigente' => 1,
    'notas_internas' => 'Nota de prueba'
];

echo "📝 Datos a insertar:<br>";
echo "<pre>" . print_r($datosTest, true) . "</pre>";

try {
    $conn->beginTransaction();

    $sql = "INSERT INTO proveedores 
            (nombre, ruc_nit, direccion, comprasucursal, vigente, notas_internas, registrado_por) 
            VALUES (:nombre, :ruc_nit, :direccion, :comprasucursal, :vigente, :notas_internas, :registrado_por)";

    $stmt = $conn->prepare($sql);

    // Usar un ID de usuario válido o 1 para prueba
    $usuarioId = 1;
    if (isset($usuario) && isset($usuario['usuario_id'])) {
        $usuarioId = $usuario['usuario_id'];
    }

    $stmt->bindValue(':nombre', $datosTest['nombre']);
    $stmt->bindValue(':ruc_nit', $datosTest['ruc_nit']);
    $stmt->bindValue(':direccion', $datosTest['direccion']);
    $stmt->bindValue(':comprasucursal', $datosTest['comprasucursal'], PDO::PARAM_INT);
    $stmt->bindValue(':vigente', $datosTest['vigente'], PDO::PARAM_INT);
    $stmt->bindValue(':notas_internas', $datosTest['notas_internas']);
    $stmt->bindValue(':registrado_por', $usuarioId, PDO::PARAM_INT);

    $stmt->execute();

    $idProveedor = $conn->lastInsertId();

    echo "✅ Proveedor insertado correctamente<br>";
    echo "🆔 ID del nuevo proveedor: $idProveedor<br>";

    // Registrar en historial
    $sqlHistorial = "INSERT INTO historial_proveedores 
                     (id_proveedor, tipo_cambio, descripcion, datos_nuevos, usuario_cambio) 
                     VALUES (?, 'datos_basicos', 'Proveedor de prueba creado', ?, ?)";

    $stmtHistorial = $conn->prepare($sqlHistorial);
    $stmtHistorial->execute([
        $idProveedor,
        json_encode($datosTest),
        $usuarioId
    ]);

    echo "✅ Historial registrado<br>";

    $conn->commit();

    echo "<br>✅ <strong>TODO FUNCIONÓ CORRECTAMENTE</strong><br>";
    echo "<p>Ahora eliminaremos este registro de prueba...</p>";

    // Limpiar registro de prueba
    $conn->prepare("DELETE FROM historial_proveedores WHERE id_proveedor = ?")->execute([$idProveedor]);
    $conn->prepare("DELETE FROM proveedores WHERE id = ?")->execute([$idProveedor]);

    echo "✅ Registro de prueba eliminado<br>";


}
catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "❌ ERROR en la base de datos: " . $e->getMessage() . "<br>";
    echo "📍 Código de error: " . $e->getCode() . "<br>";
}
catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "❌ ERROR general: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>📊 Resumen:</h3>";
echo "<p>Si llegaste hasta aquí y todos los tests pasaron, el problema está en:</p>";
echo "<ul>";
echo "<li>La estructura de \$usuario en la sesión</li>";
echo "<li>El campo 'usuario_id' no existe o tiene otro nombre</li>";
echo "<li>La validación de datos en el archivo real</li>";
echo "</ul>";
?>