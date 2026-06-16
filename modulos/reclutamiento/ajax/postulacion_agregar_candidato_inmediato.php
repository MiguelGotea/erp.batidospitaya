<?php
// postulacion_agregar_candidato_inmediato.php
// Inserta un nuevo candidato directamente en estado 'seleccionado' para la plaza indicada
// y le crea de inmediato su registro de solicitud_empleo para la fase de contratación.

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso de aprobar
    if (!tienePermiso('postulacion_plazas_activas', 'aprobar', $cargoOperario)) {
        throw new Exception('No tienes permiso para realizar esta acción.');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $idPlaza = (int) ($input['id_plaza'] ?? 0);
    $nombre = trim($input['nombre'] ?? '');
    $correo = trim($input['correo'] ?? '');
    $telefono = trim($input['telefono'] ?? '');

    if ($idPlaza <= 0) {
        throw new Exception('ID de plaza inválido.');
    }
    if (empty($nombre) || empty($correo) || empty($telefono)) {
        throw new Exception('Todos los campos son obligatorios.');
    }

    // Obtener datos de la plaza (cargo y sucursal)
    $sqlPlaza = "SELECT cargo, sucursal FROM plazas_cargos WHERE id = :id_plaza";
    $stmtPlaza = $conn->prepare($sqlPlaza);
    $stmtPlaza->bindValue(':id_plaza', $idPlaza, PDO::PARAM_INT);
    $stmtPlaza->execute();
    $plaza = $stmtPlaza->fetch(PDO::FETCH_ASSOC);

    if (!$plaza) {
        throw new Exception('La plaza de destino no existe o no está activa.');
    }

    $cargoAplicado = $plaza['cargo'];
    $sucursalAplicada = $plaza['sucursal'];

    // Iniciar transacción para asegurar consistencia
    $conn->beginTransaction();

    // 1. Insertar candidato en postulacion_plaza
    $sqlInsCandidato = "INSERT INTO postulacion_plaza (nombre, correo, telefono, status, cargo_aplicado, sucursal_aplicada, fecha_postulacion) 
                        VALUES (:nombre, :correo, :telefono, 'seleccionado', :cargo_aplicado, :sucursal_aplicada, NOW())";
    $stmtInsCandidato = $conn->prepare($sqlInsCandidato);
    $stmtInsCandidato->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmtInsCandidato->bindValue(':correo', $correo, PDO::PARAM_STR);
    $stmtInsCandidato->bindValue(':telefono', $telefono, PDO::PARAM_STR);
    $stmtInsCandidato->bindValue(':cargo_aplicado', $cargoAplicado, PDO::PARAM_INT);
    // Suportar sucursal NULL
    if ($sucursalAplicada === null) {
        $stmtInsCandidato->bindValue(':sucursal_aplicada', null, PDO::PARAM_NULL);
    } else {
        $stmtInsCandidato->bindValue(':sucursal_aplicada', $sucursalAplicada, PDO::PARAM_INT);
    }
    $stmtInsCandidato->execute();

    $idPostulacion = (int) $conn->lastInsertId();

    if ($idPostulacion <= 0) {
        throw new Exception('Error al registrar la postulación.');
    }

    // 2. Generar token y código de acceso para la solicitud de empleo
    $token = bin2hex(random_bytes(32));
    $codigoAcceso = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // 3. Crear registro en solicitud_empleo
    $sqlInsSolicitud = "INSERT INTO solicitud_empleo (id_postulacion, token, codigo_acceso, created_at) 
                        VALUES (:id_postulacion, :token, :codigo, CURRENT_TIMESTAMP)";
    $stmtInsSolicitud = $conn->prepare($sqlInsSolicitud);
    $stmtInsSolicitud->bindValue(':id_postulacion', $idPostulacion, PDO::PARAM_INT);
    $stmtInsSolicitud->bindValue(':token', $token, PDO::PARAM_STR);
    $stmtInsSolicitud->bindValue(':codigo', $codigoAcceso, PDO::PARAM_STR);
    $stmtInsSolicitud->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'id_postulacion' => $idPostulacion,
        'codigo_acceso' => $codigoAcceso,
        'token' => $token,
        'message' => 'Candidato agregado exitosamente.'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
