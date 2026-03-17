<?php
// ajax/validar_caja_chica.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Evitar que errores ensucien el JSON
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt'); // Log local para depurar

header('Content-Type: application/json');

try {
    require_once '../models/Ticket.php';
    require_once '../../../core/auth/auth.php';

    $usuario = obtenerUsuarioActual();
    if (!$usuario) {
        throw new Exception('Sesión expirada');
    }

    $ticketModel = new Ticket();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso
    if (!tienePermiso('agenda_mantenimiento', 'caja_chica', $cargoOperario)) {
        throw new Exception('No tiene permisos para realizar esta acción');
    }

    $informe_id = $_POST['informe_id'] ?? null;
    $monto = $_POST['monto'] ?? 0;

    if (!$informe_id) {
        throw new Exception('ID de informe no proporcionado');
    }

    $foto_nombre = null;
    if (isset($_FILES['foto_caja']) && $_FILES['foto_caja']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['foto_caja']['name'], PATHINFO_EXTENSION);
        $foto_nombre = 'caja_' . $informe_id . '_' . time() . '.' . $ext;
        $target = '../uploads/caja/' . $foto_nombre;
        if (!is_dir('../uploads/caja')) mkdir('../uploads/caja', 0777, true);
        move_uploaded_file($_FILES['foto_caja']['tmp_name'], $target);
    } elseif (!empty($_POST['foto_caja_cam'])) {
        $imgData = $_POST['foto_caja_cam'];
        $imgData = str_replace('data:image/jpeg;base64,', '', $imgData);
        $imgData = str_replace(' ', '+', $imgData);
        $data = base64_decode($imgData);
        $foto_nombre = 'caja_cam_' . $informe_id . '_' . time() . '.jpg';
        if (!is_dir('../uploads/caja')) mkdir('../uploads/caja', 0777, true);
        file_put_contents('../uploads/caja/' . $foto_nombre, $data);
    }

    if (!$foto_nombre) {
        throw new Exception('Debe adjuntar una foto del voucher');
    }

    $ticketModel->actualizarCajaChica($informe_id, $monto, $foto_nombre);
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    error_log("Error fatal en validar_caja_chica: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
