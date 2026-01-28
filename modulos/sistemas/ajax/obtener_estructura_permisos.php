<?php
/**
 * Obtener estructura de herramientas agrupadas
 * Retorna: { "Grupo1": [{id, nombre}], "Grupo2": [...] }
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    
    // Verificar acceso
    if (!tienePermiso('gestion_permisos', 'vista', $cargoOperario)) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para acceder'
        ]);
        exit;
    }
    
    // Obtener tipo de componente (herramienta, indicador, balance)
    $tipoComponente = isset($_GET['tipo_componente']) ? $_GET['tipo_componente'] : 'herramienta';
    
    // Validar tipo de componente
    $tiposPermitidos = ['herramienta', 'indicador', 'balance'];
    if (!in_array($tipoComponente, $tiposPermitidos)) {
        $tipoComponente = 'herramienta';
    }
    
    // Obtener todas las herramientas agrupadas filtradas por tipo
    $sql = "SELECT id, nombre, titulo, grupo, descripcion, url_real, icono, orden
            FROM tools_erp
            WHERE tipo_componente = :tipo_componente AND activo = 1
            ORDER BY grupo ASC, orden ASC, titulo ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':tipo_componente' => $tipoComponente]);
    $herramientas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por 'grupo'
    $gruposAgrupados = [];
    foreach ($herramientas as $herramienta) {
        $grupo = $herramienta['grupo'] ?? 'Sin Grupo';
        
        if (!isset($gruposAgrupados[$grupo])) {
            $gruposAgrupados[$grupo] = [];
        }
        
        // Procesar icono
        $icono = 'bi bi-file-earmark-code'; // default
        if (!empty($herramienta['icono'])) {
            if (strpos($herramienta['icono'], 'fa-') !== false) {
                $icono = 'fa ' . $herramienta['icono'];
            } elseif (strpos($herramienta['icono'], 'bi-') !== false) {
                $icono = 'bi ' . $herramienta['icono'];
            } elseif (strpos($herramienta['icono'], 'fas ') !== false || strpos($herramienta['icono'], 'bi ') !== false) {
                $icono = $herramienta['icono']; // ya tiene prefijo
            }
        }
        
        $gruposAgrupados[$grupo][] = [
            'id' => (int)$herramienta['id'],
            'nombre' => $herramienta['nombre'],
            'titulo' => $herramienta['titulo'],
            'descripcion' => $herramienta['descripcion'],
            'url_real' => $herramienta['url_real'],
            'icono' => $icono
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $gruposAgrupados
    ]);
    
} catch (Exception $e) {
    error_log("Error en obtener_estructura_permisos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar la estructura de herramientas'
    ]);
}
?>