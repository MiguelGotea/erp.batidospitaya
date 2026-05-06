<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar que solo Operaciones pueda acceder
if (!verificarAccesoCargo([11, 13, 28, 39, 30, 37, 49])) {
    header('Location: /index.php');
    exit();
}

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_tardanza'])) {
    try {
        $id = (int) $_POST['id'];

        // NUEVA VALIDACIÓN: Obtener información de la tardanza antes de editar
        $stmt = $conn->prepare("SELECT cod_operario, fecha_tardanza FROM TardanzasManuales WHERE id = ?");
        $stmt->execute([$id]);
        $tardanzaExistente = $stmt->fetch();

        if (!$tardanzaExistente) {
            $_SESSION['error'] = 'Tardanza no encontrada';
            $params = [];
            if (isset($_POST['sucursal']))
                $params['sucursal'] = $_POST['sucursal'];
            if (isset($_POST['desde']))
                $params['desde'] = $_POST['desde'];
            if (isset($_POST['hasta']))
                $params['hasta'] = $_POST['hasta'];
            header('Location: tardanzas_manual.php?' . http_build_query($params));
            exit();
        }

        // NUEVA VALIDACIÓN: Verificar que la fecha de tardanza no sea posterior a liquidación
        if (fechaPosteriorLiquidacion($tardanzaExistente['cod_operario'], $tardanzaExistente['fecha_tardanza'])) {
            $_SESSION['error'] = 'No se puede editar: La tardanza es posterior a la fecha de liquidación del colaborador';
            $params = [];
            if (isset($_POST['sucursal']))
                $params['sucursal'] = $_POST['sucursal'];
            if (isset($_POST['desde']))
                $params['desde'] = $_POST['desde'];
            if (isset($_POST['hasta']))
                $params['hasta'] = $_POST['hasta'];
            header('Location: tardanzas_manual.php?' . http_build_query($params));
            exit();
        }

        // NUEVA VALIDACIÓN: Verificar que el operario tenga contrato
        if (!operarioTieneContrato($tardanzaExistente['cod_operario'])) {
            $_SESSION['error'] = 'No se puede editar: El colaborador no tiene registro de contrato. Contactar con RH.';
            $params = [];
            if (isset($_POST['sucursal']))
                $params['sucursal'] = $_POST['sucursal'];
            if (isset($_POST['desde']))
                $params['desde'] = $_POST['desde'];
            if (isset($_POST['hasta']))
                $params['hasta'] = $_POST['hasta'];
            header('Location: tardanzas_manual.php?' . http_build_query($params));
            exit();
        }

        // Continuar con actualización...
        $estado = $_POST['estado'];
        $observaciones = $_POST['observaciones'] ?? null;

        $stmt = $conn->prepare("
            UPDATE TardanzasManuales 
            SET estado = ?, 
                observaciones = ?,
                actualizado_por = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $estado,
            $observaciones,
            $_SESSION['usuario_id'],
            $id
        ]);

        $_SESSION['exito'] = 'Tardanza manual actualizada correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al actualizar la tardanza manual: ' . $e->getMessage();
    }

    // Construir parámetros de redirección
    $params = [];
    if (!empty($_POST['sucursal']))
        $params['sucursal'] = $_POST['sucursal'];
    if (!empty($_POST['desde']))
        $params['desde'] = $_POST['desde'];
    if (!empty($_POST['hasta']))
        $params['hasta'] = $_POST['hasta'];

    // Redirigir manteniendo los filtros
    header('Location: tardanzas_manual.php?' . http_build_query($params));
    exit();
}

// Si no es POST, redirigir con parámetros si existen
$params = [];
if (!empty($_GET['sucursal']))
    $params['sucursal'] = $_GET['sucursal'];
if (!empty($_GET['desde']))
    $params['desde'] = $_GET['desde'];
if (!empty($_GET['hasta']))
    $params['hasta'] = $_GET['hasta'];

header('Location: tardanzas_manual.php?' . http_build_query($params));
exit();
?>