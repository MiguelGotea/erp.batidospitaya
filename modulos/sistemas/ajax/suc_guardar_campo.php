<?php
/**
 * suc_guardar_campo.php
 * POST: Auto-save de un campo individual de la tabla sucursales
 * Permiso requerido: configuracion_sucursales > edicion
 *
 * Recibe (POST):
 *   id_sucursal  int
 *   campo        string  (whitelist estricta)
 *   valor        mixed
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

// ── Whitelist estricta de campos editables ──────────────────
$CAMPOS_PERMITIDOS = [
    // Generales
    'nombre'             => 'string',
    'ip_direccion'       => 'string',
    'ip_impresora'       => 'string',
    'telefono'           => 'string',
    'whatsapp'           => 'string',
    'email'              => 'string',
    'departamento'       => 'string',
    'cod_departamento'   => 'int',
    'Fecha_Apertura'     => 'date',
    'Fecha_Cierre'       => 'date',
    // Estado
    'activa'             => 'bool',
    'sucursal'           => 'bool',
    'VMTAP'              => 'bool',
    'viatico_nocturno'   => 'int',
    'cod_googlebusiness' => 'string',
    'cookie_token'       => 'string',
    'pos_cookie_token'   => 'string',
    // Ubicación
    'Latitude'           => 'float',
    'Longitude'          => 'float',
];

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('configuracion_sucursales', 'edicion', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de edición']);
        exit;
    }

    $id    = isset($_POST['id_sucursal']) ? (int)$_POST['id_sucursal'] : 0;
    $campo = isset($_POST['campo'])       ? trim($_POST['campo'])       : '';
    $valor = isset($_POST['valor'])       ? $_POST['valor']             : null;

    // Validaciones básicas
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de sucursal inválido']);
        exit;
    }

    if (!array_key_exists($campo, $CAMPOS_PERMITIDOS)) {
        echo json_encode(['success' => false, 'message' => "Campo '$campo' no permitido"]);
        exit;
    }

    // Validar y castear valor según tipo
    $tipo = $CAMPOS_PERMITIDOS[$campo];
    switch ($tipo) {
        case 'int':
            $valor = $valor !== '' && $valor !== null ? (int)$valor : 0;
            break;
        case 'float':
            $valor = $valor !== '' && $valor !== null ? (float)$valor : null;
            break;
        case 'bool':
            $valor = in_array($valor, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
            break;
        case 'date':
            if (!empty($valor)) {
                $d = DateTime::createFromFormat('Y-m-d', $valor);
                if (!$d) {
                    echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido (esperado yyyy-mm-dd)']);
                    exit;
                }
            } else {
                $valor = null;
            }
            break;
        case 'string':
        default:
            $valor = $valor !== null ? trim((string)$valor) : null;
            break;
    }

    // Validación especial: Fecha_Cierre >= Fecha_Apertura
    if ($campo === 'Fecha_Cierre' && $valor !== null) {
        $sqlFecha = "SELECT Fecha_Apertura FROM sucursales WHERE id = :id LIMIT 1";
        $stmtF = $conn->prepare($sqlFecha);
        $stmtF->execute([':id' => $id]);
        $rowF = $stmtF->fetch(PDO::FETCH_ASSOC);
        if ($rowF && !empty($rowF['Fecha_Apertura'])) {
            if ($valor < $rowF['Fecha_Apertura']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'La fecha de cierre no puede ser anterior a la fecha de apertura'
                ]);
                exit;
            }
        }
    }
    if ($campo === 'Fecha_Apertura' && $valor !== null) {
        $sqlFecha = "SELECT Fecha_Cierre FROM sucursales WHERE id = :id LIMIT 1";
        $stmtF = $conn->prepare($sqlFecha);
        $stmtF->execute([':id' => $id]);
        $rowF = $stmtF->fetch(PDO::FETCH_ASSOC);
        if ($rowF && !empty($rowF['Fecha_Cierre'])) {
            if ($valor > $rowF['Fecha_Cierre']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'La fecha de apertura no puede ser posterior a la fecha de cierre'
                ]);
                exit;
            }
        }
    }

    // Verificar que la sucursal existe
    $stmtCheck = $conn->prepare("SELECT id FROM sucursales WHERE id = :id LIMIT 1");
    $stmtCheck->execute([':id' => $id]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada']);
        exit;
    }

    // Ejecutar UPDATE con campo dinámico seguro (whitelisted)
    $sql = "UPDATE sucursales SET `$campo` = :valor WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':valor' => $valor, ':id' => $id]);

    echo json_encode([
        'success' => true,
        'message' => 'Guardado correctamente',
        'campo'   => $campo,
        'valor'   => $valor
    ]);

} catch (Exception $e) {
    error_log("Error en suc_guardar_campo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
?>
