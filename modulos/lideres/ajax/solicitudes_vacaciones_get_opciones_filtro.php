<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// ajax/solicitudes_vacaciones_get_opciones_filtro.php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    verificarAutenticacion();
    
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    $opciones = [];
    
    // Obtener cargos del usuario
    $cargosUsuario = obtenerCargosUsuario($_SESSION['usuario_id']);
    $esCargo13 = in_array(13, $cargosUsuario);
    $esCargo28 = in_array(28, $cargosUsuario);
    $esCargo16 = in_array(16, $cargosUsuario);
    
    if ($columna === 'sucursal') {
        // Obtener sucursales según permisos
        if ($esCargo13 || $esCargo28 || $esCargo16) {
            // RH y Gerencia ven todas las sucursales
            $stmt = $conn->prepare("
                SELECT DISTINCT s.codigo, s.nombre 
                FROM sucursales s 
                WHERE s.codigo IN (
                    SELECT DISTINCT cod_sucursal 
                    FROM solicitudes_vacaciones
                )
                ORDER BY s.nombre
            ");
            $stmt->execute();
        } else {
            // Otros usuarios solo sus sucursales
            $stmt = $conn->prepare("
                SELECT DISTINCT s.codigo, s.nombre 
                FROM sucursales s 
                JOIN AsignacionNivelesCargos anc ON s.codigo = anc.Sucursal
                WHERE anc.CodOperario = ?
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                ORDER BY s.nombre
            ");
            $stmt->execute([$_SESSION['usuario_id']]);
        }
        
        while ($row = $stmt->fetch()) {
            $opciones[] = [
                'valor' => $row['codigo'],
                'texto' => $row['nombre']
            ];
        }
        
    } elseif ($columna === 'estado') {
        $opciones = [
            ['valor' => 'Pendiente', 'texto' => 'Pendiente'],
            ['valor' => 'Aprobado_Operaciones', 'texto' => 'Aprobado Operaciones'],
            ['valor' => 'Aprobado_RH', 'texto' => 'Aprobado RH'],
            ['valor' => 'Rechazado', 'texto' => 'Rechazado']
        ];
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