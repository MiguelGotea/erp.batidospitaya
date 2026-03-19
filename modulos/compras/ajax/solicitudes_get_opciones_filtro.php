<?php
// solicitudes_get_opciones_filtro.php
require_once '../../../core/database/conexion.php';
require_once '../../../core/helpers/funciones.php';
require_once '../../../core/auth/auth.php';

verificarAutenticacion();

header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    // Filtro de estado
    if ($columna === 'estado') {
        $opciones = [
            ['valor' => 'pendiente', 'texto' => 'Pendiente'],
            ['valor' => 'aprobada', 'texto' => 'Aprobada'],
            ['valor' => 'rechazada', 'texto' => 'Rechazada'],
            ['valor' => 'completada', 'texto' => 'Completada'],
            ['valor' => 'cancelada', 'texto' => 'Cancelada']
        ];
    }
    
    // Filtro de gerencia (Opción C: ambas opciones)
    if ($columna === 'gerente_aprobador_nombre') {
        // Opciones especiales primero
        $opciones[] = ['valor' => 'aprobadas', 'texto' => '✓ Aprobadas por Gerencia'];
        $opciones[] = ['valor' => 'sin_aprobar', 'texto' => '✗ Sin Aprobar'];
        
        // Obtener lista de gerentes que han aprobado solicitudes
        $sql = "SELECT DISTINCT gerente_aprobador_nombre 
                FROM solicitudes_cotizacion 
                WHERE gerente_aprobador_nombre IS NOT NULL 
                ORDER BY gerente_aprobador_nombre";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $gerentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($gerentes as $gerente) {
            $opciones[] = [
                'valor' => $gerente,
                'texto' => '👤 ' . $gerente
            ];
        }
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