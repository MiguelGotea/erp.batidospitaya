<?php
// compra_local_planificador_stock_get_perfiles.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $stmt = $conn->query("SELECT 
                            id, 
                            nombre, 
                            frecuencia_semanas as frecuencia, 
                            semana_referencia,
                            lunes, martes, miercoles, jueves, viernes, sabado, domingo
                          FROM compra_local_perfiles_despacho 
                          ORDER BY nombre");
    $perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'perfiles' => $perfiles
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
