<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }

    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    $opciones = [];

    if ($columna === 'nombre_proveedor') {
        $sql = "SELECT DISTINCT p.id AS valor, p.nombre AS texto
                FROM pos_facturas f
                INNER JOIN proveedores p ON f.id_proveedor = p.id
                WHERE p.nombre IS NOT NULL AND p.nombre <> ''
                ORDER BY p.nombre ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($columna === 'estado') {
        $opciones = [
            ['valor' => 'activa', 'texto' => 'Activa'],
            ['valor' => 'anulada', 'texto' => 'Anulada']
        ];
    } elseif ($columna === 'registrado_por_nombre') {
        $sql = "SELECT DISTINCT o.CodOperario AS valor, CONCAT(o.Nombre, ' ', o.Apellido) AS texto
                FROM pos_facturas f
                INNER JOIN Operarios o ON f.registrado_por = o.CodOperario
                ORDER BY texto ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'opciones' => $opciones
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
