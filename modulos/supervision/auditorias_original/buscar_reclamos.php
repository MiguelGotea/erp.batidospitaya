<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once 'auth.php';
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditor�a
require_once 'conexion.php';

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

header('Content-Type: application/json');

$searchTerm = $_GET['q'] ?? '';
$searchTerm = trim($searchTerm);

if (empty($searchTerm)) {
    echo json_encode([]);
    exit();
}

try {
    $query = "SELECT r.id, r.sucursal, DATE_FORMAT(r.fecha_evento, '%d-%b-%y') as fecha_evento, 
              SUBSTRING(r.descripcion, 1, 100) as descripcion 
              FROM reclamos r 
              LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
              WHERE ri.id IS NULL 
              AND (r.id = :id OR r.sucursal LIKE :sucursal OR r.descripcion LIKE :descripcion)
              ORDER BY r.fecha_evento DESC 
              LIMIT 20";
    
    $stmt = $conn->prepare($query);
    
    // Buscar por ID exacto si es numérico
    if (is_numeric($searchTerm)) {
        $stmt->bindValue(':id', (int)$searchTerm);
    } else {
        $stmt->bindValue(':id', 0);
    }
    
    $stmt->bindValue(':sucursal', '%' . $searchTerm . '%');
    $stmt->bindValue(':descripcion', '%' . $searchTerm . '%');
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
