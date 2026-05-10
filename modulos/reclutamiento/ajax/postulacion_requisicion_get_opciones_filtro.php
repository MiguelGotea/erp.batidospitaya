<?php
// postulacion_requisicion_get_opciones_filtro.php

require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    if (!$usuario) {
        throw new Exception('No autorizado');
    }

    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    $opciones = [];

    switch ($columna) {
        case 'tipo_plaza':
            $opciones = [
                ['valor' => 'Permanente', 'texto' => 'Permanente'],
                ['valor' => 'Temporal', 'texto' => 'Temporal']
            ];
            break;

        case 'nivel_urgencia':
            $opciones = [
                ['valor' => '1', 'texto' => '1 - No urgente'],
                ['valor' => '2', 'texto' => '2 - Medio'],
                ['valor' => '3', 'texto' => '3 - Urgente'],
                ['valor' => '4', 'texto' => '4 - Crítico']
            ];
            break;

        case 'status':
            $opciones = [
                ['valor' => 'Solicitado', 'texto' => 'Solicitado'],
                ['valor' => 'Aprobado', 'texto' => 'Aprobado'],
                ['valor' => 'Rechazado', 'texto' => 'Rechazado']
            ];
            break;

        default:
            // Intentar obtener valores únicos de la base de datos para otras columnas si fuera necesario
            break;
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