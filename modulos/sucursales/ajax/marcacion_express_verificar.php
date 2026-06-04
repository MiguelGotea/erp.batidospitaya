<?php
// erp.batidospitaya/modulos/sucursales/ajax/marcacion_express_verificar.php
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/helpers/funciones.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';

// 1. Identificar la sucursal por la cookie
$tokenCookie = $_COOKIE['erp_device_token'] ?? null;
if (empty($tokenCookie)) {
    echo json_encode(['success' => false, 'message' => 'Dispositivo no autorizado']);
    exit();
}

global $conn;
try {
    $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE cookie_token = ? LIMIT 1");
    $stmt->execute([$tokenCookie]);
    $sucursal = $stmt->fetch();

    if (!$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Dispositivo no configurado o token inválido']);
        exit();
    }

    $codSucursalReal = $sucursal['codigo'];
    $codSucursal = ($codSucursalReal == 6 || $codSucursalReal == 18) ? 18 : $codSucursalReal;

    // 2. Obtener clave del POST
    $clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';
    if (empty($clave)) {
        echo json_encode(['success' => false, 'message' => 'Ingrese su contraseña']);
        exit();
    }

    // 3. Buscar al operario por clave en esta sucursal (combinando 6 y 18)
    // El operario debe estar operativo (Operativo = 1) y tener una asignación activa en esta sucursal
    $sql = "SELECT o.CodOperario, o.Nombre, o.Apellido
            FROM Operarios o
            JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                AND anc.Fecha <= CURDATE()
            WHERE (o.clave = ? OR (o.clave_hash IS NOT NULL AND ? = o.clave_hash))
            AND (anc.Sucursal = ? OR (? = 18 AND anc.Sucursal = 6))
            AND o.Operativo = 1";

    $stmtOperario = $conn->prepare($sql);
    $stmtOperario->execute([$clave, $clave, $codSucursal, $codSucursal]);
    $operarios = $stmtOperario->fetchAll();

    if (count($operarios) === 0) {
        echo json_encode(['success' => false, 'message' => 'Contraseña no coincide con ningún colaborador en esta sucursal']);
        exit();
    }

    if (count($operarios) > 1) {
        // En caso de que compartan contraseña en la misma sucursal
        echo json_encode(['success' => false, 'message' => 'Múltiples colaboradores coinciden con esta clave. Favor cambiar su contraseña con Recursos Humanos.']);
        exit();
    }

    $operario = $operarios[0];
    $codOperario = $operario['CodOperario'];

    // 4. Verificar última marcación de hoy para cambiar texto del botón
    $fechaActual = date('Y-m-d');
    $sqlMarcacion = "SELECT * FROM marcaciones 
                     WHERE CodOperario = ? 
                     AND (sucursal_codigo = ? OR (? = 18 AND sucursal_codigo = 6))
                     AND fecha = ?
                     ORDER BY hora_ingreso DESC 
                     LIMIT 1";
    $stmtMarc = $conn->prepare($sqlMarcacion);
    $stmtMarc->execute([$codOperario, $codSucursal, $codSucursal, $fechaActual]);
    $ultimaMarcacion = $stmtMarc->fetch();

    $tipoMarc = 'entrada';
    if ($ultimaMarcacion && empty($ultimaMarcacion['hora_salida'])) {
        $tipoMarc = 'salida';
    }

    $nombreCompleto = obtenerNombreCompletoOperario($operario);

    echo json_encode([
        'success' => true,
        'nombre' => $nombreCompleto,
        'tipoMarcacion' => $tipoMarc
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de sistema: ' . $e->getMessage()]);
}
