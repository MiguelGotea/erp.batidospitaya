<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    $opciones = [];

    if ($columna === 'estado') {
        $stmt = $conn->query("SELECT DISTINCT estado FROM reembolsos_solicitudes WHERE estado IS NOT NULL ORDER BY estado ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $opciones[] = [
                'valor' => $row['estado'],
                'texto' => strtoupper($row['estado'])
            ];
        }
    } elseif ($columna === 'ceco') {
        $stmt = $conn->query("
            SELECT DISTINCT s.ceco as valor, CONCAT(cc.Codigo, ' - ', cc.Nombre) as texto 
            FROM reembolsos_solicitudes s
            JOIN CentroCostos cc ON s.ceco = cc.Codigo
            ORDER BY cc.Nombre ASC
        ");
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
