<?php
/**
 * suc_get_departamentos.php
 * GET: Lista todos los departamentos de la tabla departamentos
 * Permiso requerido: configuracion_sucursales > vista
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('configuracion_sucursales', 'vista', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de acceso']);
        exit;
    }

    // Verificar si la tabla existe, si no, crearla
    $checkTable = $conn->query("SHOW TABLES LIKE 'departamentos'")->fetch();
    if (!$checkTable) {
        $sqlCreate = "CREATE TABLE `departamentos` (
          `codigo` int(11) NOT NULL,
          `nombre` varchar(50) NOT NULL,
          `tiene_sucursal` tinyint(1) DEFAULT 0 COMMENT '¿Este departamento tiene sucursal?',
          `viatico_nocturno` decimal(10,2) DEFAULT NULL COMMENT 'Monto de viático nocturno. NULL = no aplica',
          `horario_nocturno_viatico` time DEFAULT NULL,
          `viatico_diurno` decimal(10,2) DEFAULT NULL,
          `horario_mananero_viatico` time DEFAULT NULL,
          PRIMARY KEY (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $conn->exec($sqlCreate);
    }

    // Si la tabla está vacía, poblarla con los datos que estaban hardcodeados
    $count = $conn->query("SELECT COUNT(*) FROM departamentos")->fetchColumn();
    if ($count == 0) {
        $initialDptos = [
            [1, 'Boaco'], [2, 'Carazo'], [3, 'Chinandega'], [4, 'Chontales'],
            [5, 'Estelí'], [6, 'Granada'], [7, 'Jinotega'], [8, 'León'],
            [9, 'Madriz'], [10, 'Managua'], [11, 'Masaya'], [12, 'Matagalpa'],
            [13, 'Nueva Segovia'], [14, 'Río San Juan'], [15, 'Rivas'],
            [16, 'RAAN'], [17, 'RAAS']
        ];
        $stmtIns = $conn->prepare("INSERT INTO departamentos (codigo, nombre) VALUES (?, ?)");
        foreach ($initialDptos as $d) {
            $stmtIns->execute($d);
        }
    }

    $sql = "SELECT codigo, nombre FROM departamentos ORDER BY nombre ASC";
    $stmt = $conn->query($sql);
    $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $departamentos]);

} catch (Exception $e) {
    error_log("Error en suc_get_departamentos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar departamentos: ' . $e->getMessage()]);
}
?>
