<?php
// ajax/hcd_get_opciones_filtro.php
// Devuelve los valores únicos para los filtros de tipo "list" del historial de cierres.
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = $_POST['columna'] ?? '';
    $opciones = [];

    if ($columna === 'nombre_sucursal') {
        // Sucursales que tienen al menos un cierre registrado
        $sql = "SELECT DISTINCT COALESCE(s.nombre, CONCAT('Sucursal ', cd.Sucursal)) AS valor
                FROM msaccess_masivo_CierreDiario cd
                LEFT JOIN sucursales s ON s.codigo = cd.Sucursal
                ORDER BY valor ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $opciones[] = ['valor' => $r['valor'], 'texto' => $r['valor']];
        }
    }

    echo json_encode(['success' => true, 'opciones' => $opciones]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
