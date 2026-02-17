<?php
// compra_local_gestion_perfiles_get.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM compra_local_configuracion_despacho c WHERE c.id_perfil = p.id) as total_productos
            FROM compra_local_perfiles_despacho p 
            ORDER BY p.nombre";
    $stmt = $conn->query($sql);
    $perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'perfiles' => $perfiles
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
