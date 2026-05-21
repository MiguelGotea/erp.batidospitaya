<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

header('Content-Type: application/json');

try {
    $query = "SELECT r.id, r.sucursal, DATE_FORMAT(r.fecha_evento, '%d-%b-%y') as fecha_evento, 
              SUBSTRING(r.descripcion, 1, 100) as descripcion 
              FROM reclamos r 
              LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
              WHERE ri.id IS NULL 
              ORDER BY r.fecha_evento DESC 
              LIMIT 1000"; // Límite para no sobrecargar memoria
    
    $stmt = $conn->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
