<?php
// ajax/hcd_anular_cierre.php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('balance_cierre_diario', 'anular', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para anular cierres.']);
        exit;
    }

    $fecha    = $_POST['fecha'] ?? null;
    $sucursal = $_POST['sucursal'] ?? null;
    $codigo_cierre = $_POST['codigo_cierre'] ?? null;

    if (!$fecha || (!$sucursal && $sucursal !== '0') || !$codigo_cierre) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos.']);
        exit;
    }

    // Crear la tabla si no existe
    $sqlCreate = "CREATE TABLE IF NOT EXISTS anulacion_cierres_diarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        CodigoCierre INT NOT NULL,
        Sucursal VARCHAR(50) NOT NULL,
        fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
        status INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->exec($sqlCreate);

    // Obtener todos los cierres del día para la sucursal, ordenados por CodigoCierre ASC
    $sql = "SELECT CodigoCierre, HoraInicial, HoraFinal 
            FROM msaccess_masivo_CierreDiario 
            WHERE Fecha = :fecha AND Sucursal = :sucursal
            ORDER BY CodigoCierre ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['fecha' => $fecha, 'sucursal' => $sucursal]);
    $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($lista) === 0) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron cierres para el día y sucursal.']);
        exit;
    }

    // Convertir hora a minutos para el agrupamiento
    if (!function_exists('horaAMinAux')) {
        function horaAMinAux($h) {
            if (!$h) return 0;
            $parts = explode(':', $h);
            return (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
        }
    }

    // Logica de agrupacion igual a hcd_get_datos.php
    $grupos = [];
    foreach ($lista as $c) {
        $minC = horaAMinAux($c['HoraInicial']);
        $encontrado = false;
        foreach ($grupos as &$g) {
            $minRef = horaAMinAux($g['todos'][0]['HoraInicial']);
            if (abs($minC - $minRef) <= 30) {
                $g['todos'][] = $c;
                $encontrado = true;
                break;
            }
        }
        unset($g);
        if (!$encontrado) {
            $grupos[] = ['todos' => [$c]];
        }
    }

    // Encontrar el grupo al que pertenece $codigo_cierre
    $grupoAfectado = null;
    foreach ($grupos as $g) {
        foreach ($g['todos'] as $c) {
            if ((string)$c['CodigoCierre'] === (string)$codigo_cierre) {
                $grupoAfectado = $g;
                break 2;
            }
        }
    }

    if (!$grupoAfectado) {
        echo json_encode(['success' => false, 'message' => 'No se pudo identificar el grupo de cierres afectado.']);
        exit;
    }

    // Insertar todos los CodigoCierre del grupo afectado a la cola de anulación
    $conn->beginTransaction();
    $stmtInsert = $conn->prepare("INSERT INTO anulacion_cierres_diarios (CodigoCierre, Sucursal, status) VALUES (:codigo, :sucursal, 0)");
    
    $insertados = 0;
    foreach ($grupoAfectado['todos'] as $c) {
        // Verificar si ya está encolado pendiente
        $stmtCheck = $conn->prepare("SELECT id FROM anulacion_cierres_diarios WHERE CodigoCierre = :codigo AND status = 0");
        $stmtCheck->execute(['codigo' => $c['CodigoCierre']]);
        if (!$stmtCheck->fetch()) {
            $stmtInsert->execute([
                'codigo' => $c['CodigoCierre'],
                'sucursal' => $sucursal
            ]);
            $insertados++;
        }
    }
    
    $conn->commit();

    echo json_encode(['success' => true, 'message' => "Se encolaron $insertados cierres (incluyendo precierres) para su anulación."]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
