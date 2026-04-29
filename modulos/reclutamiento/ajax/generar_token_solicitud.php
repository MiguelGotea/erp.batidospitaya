<?php
// generar_token_solicitud.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $idPostulacion = (int) ($input['id_postulacion'] ?? 0);

    if ($idPostulacion <= 0) {
        throw new Exception('ID de postulación inválido');
    }

    // Verificar si ya tiene una solicitud
    $sqlCheck = "SELECT token, codigo_acceso FROM solicitud_empleo WHERE id_postulacion = :id";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindValue(':id', $idPostulacion, PDO::PARAM_INT);
    $stmtCheck->execute();
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $codigo = $existing['codigo_acceso'];

        // Si ya existe pero no tiene código (registros viejos), generarlo y guardarlo
        if (empty($codigo)) {
            $codigo = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmtUpd = $conn->prepare("UPDATE solicitud_empleo SET codigo_acceso = :codigo WHERE id_postulacion = :id");
            $stmtUpd->execute([':codigo' => $codigo, ':id' => $idPostulacion]);
        }

        echo json_encode([
            'success' => true,
            'token' => $existing['token'],
            'codigo_acceso' => $codigo
        ]);
        exit();
    }

    // Generar nuevo token seguro y código de 6 dígitos
    $token = bin2hex(random_bytes(32));
    $codigoAcceso = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Crear registro en solicitud_empleo
    $sqlIns = "INSERT INTO solicitud_empleo (id_postulacion, token, codigo_acceso, created_at) 
               VALUES (:id, :token, :codigo, CURRENT_TIMESTAMP)";
    $stmtIns = $conn->prepare($sqlIns);
    $stmtIns->bindValue(':id', $idPostulacion, PDO::PARAM_INT);
    $stmtIns->bindValue(':token', $token, PDO::PARAM_STR);
    $stmtIns->bindValue(':codigo', $codigoAcceso, PDO::PARAM_STR);

    if ($stmtIns->execute()) {
        echo json_encode(['success' => true, 'token' => $token, 'codigo_acceso' => $codigoAcceso]);
    } else {
        throw new Exception('Error al insertar el registro de solicitud');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>