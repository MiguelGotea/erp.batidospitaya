<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar autenticación
verificarAutenticacion();

/**
 * Obtiene una categoría específica por ID
 */
function obtenerCategoriaPorId($idCategoria) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT oc.*, co.NombreCategoria, co.Peso
        FROM OperariosCategorias oc
        JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
        WHERE oc.id = ?
    ");
    $stmt->execute([$idCategoria]);
    return $stmt->fetch();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID de categoría no proporcionado']);
    exit();
}

$idCategoria = intval($_GET['id']);

try {
    // Obtener categoría
    $categoria = obtenerCategoriaPorId($idCategoria);

    if (!$categoria) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Categoría no encontrada']);
        exit();
    }

    header('Content-Type: application/json');
    echo json_encode($categoria);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
    error_log('Error en obtener_categoria.php: ' . $e->getMessage());
}