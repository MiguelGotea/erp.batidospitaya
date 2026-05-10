<?php
require_once '../../core/auth/auth.php';
verificarAutenticacion();

// Verificar que solo RH pueda acceder
if (!verificarAccesoCargo([13, 39, 30, 37, 28])) {
    header('Location: /index.php');
    exit();
}

/**
 * Obtiene el porcentaje de pago para un tipo de falta específico
 */
function obtenerPorcentajePagoTipoFalta($tipoFalta) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT porcentaje_pago 
        FROM tipos_falta 
        WHERE codigo = ? 
        LIMIT 1
    ");
    $stmt->execute([$tipoFalta]);
    $result = $stmt->fetch();
    
    return $result ? $result['porcentaje_pago'] : 0;
}

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_falta'])) {
    try {
        $id = (int)$_POST['id'];
        
        // NUEVA VALIDACIÓN: Obtener información de la falta antes de editar
        $stmt = $conn->prepare("SELECT cod_operario, fecha_falta FROM faltas_manual WHERE id = ?");
        $stmt->execute([$id]);
        $faltaExistente = $stmt->fetch();
        
        if (!$faltaExistente) {
            $_SESSION['error'] = 'Falta no encontrada';
            $params = [];
            if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
            header('Location: faltas_manual.php?' . http_build_query($params));
            exit();
        }
        
        // NUEVA VALIDACIÓN: Verificar que la fecha de falta no sea posterior a liquidación
        if (fechaPosteriorLiquidacion($faltaExistente['cod_operario'], $faltaExistente['fecha_falta'])) {
            $_SESSION['error'] = 'No se puede editar: La falta es posterior a la fecha de liquidación del colaborador';
            $params = [];
            if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
            header('Location: faltas_manual.php?' . http_build_query($params));
            exit();
        }
        
        // NUEVA VALIDACIÓN: Verificar que el operario tenga contrato
        if (!operarioTieneContrato($faltaExistente['cod_operario'])) {
            $_SESSION['error'] = 'No se puede editar: El colaborador no tiene registro de contrato. Contactar con RH.';
            $params = [];
            if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
            if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
            if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
            if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
            header('Location: faltas_manual.php?' . http_build_query($params));
            exit();
        }
        
        $tipoFalta = $_POST['tipo_falta'];
        $observaciones_rrhh = $_POST['observaciones_rrhh'] ?? null;
        
        // Validar que las observaciones RRHH no estén vacías
        if (empty($observaciones_rrhh)) {
            $_SESSION['error'] = 'El campo Observaciones RRHH es obligatorio';
            header('Location: faltas_manual.php?' . http_build_query($_GET));
            exit();
        }
        
        // OBTENER EL NUEVO PORCENTAJE BASADO EN EL TIPO DE FALTA
        $porcentajePago = obtenerPorcentajePagoTipoFalta($tipoFalta);
        
        // Actualizar incluyendo el porcentaje de pago
        $stmt = $conn->prepare("
            UPDATE faltas_manual 
            SET tipo_falta = ?, 
                observaciones_rrhh = ?,
                porcentaje_pago = ?,
                actualizado_por = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $tipoFalta,
            $observaciones_rrhh,
            $porcentajePago, // NUEVO: porcentaje actualizado
            $_SESSION['usuario_id'],
            $id
        ]);
        
        $_SESSION['exito'] = 'Falta manual actualizada correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al actualizar la falta manual: ' . $e->getMessage();
    }
    
    // Redirigir manteniendo TODOS los filtros
    $params = [];
    if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
    if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
    if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
    if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];
    
    header('Location: faltas_manual.php?' . http_build_query($params));
    exit();
}

// Si no es POST, redirigir manteniendo filtros también
$params = [];
if (isset($_GET['sucursal'])) $params['sucursal'] = $_GET['sucursal'];
if (isset($_GET['desde'])) $params['desde'] = $_GET['desde'];
if (isset($_GET['hasta'])) $params['hasta'] = $_GET['hasta'];
if (isset($_GET['operario']) && $_GET['operario'] != 0) $params['operario'] = $_GET['operario'];

header('Location: faltas_manual.php?' . http_build_query($params));
exit();
?>