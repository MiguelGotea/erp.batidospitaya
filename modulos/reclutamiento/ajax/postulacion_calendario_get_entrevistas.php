<?php
// postulacion_calendario_get_entrevistas.php

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $mes = (int) ($input['mes'] ?? date('n'));
    $anio = (int) ($input['anio'] ?? date('Y'));

    // Obtener todas las entrevistas programadas
    $where = "WHERE resultado_entrevista = 'pendiente'";
    $params = [];

    // Filtrado por reclutador (si es cargo 13 puede ver todos o uno específico)
    // Para otros niveles, solo ven lo suyo
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    $codOperario = $usuario['CodOperario'];

    $entrevistadorId = $input['entrevistador_id'] ?? 'todos';
    $puedeFiltrarTodo = in_array($cargoOperario, [13, 16, 49]) || tienePermiso('postulacion_calendario', 'filtrar_entrevistadores', $cargoOperario);

    if ($puedeFiltrarTodo) {
        if ($entrevistadorId !== 'todos') {
            $where .= " AND ec.reclutador_entrevista = :reclutador";
            $params[':reclutador'] = (int) $entrevistadorId;
        }
    } else {
        $where .= " AND ec.reclutador_entrevista = :reclutador";
        $params[':reclutador'] = $codOperario;
    }

    $sql = "SELECT 
                ec.id,
                ec.id_postulacion,
                ec.fecha_entrevista,
                ec.hora_entrevista,
                ec.modalidad_entrevista,
                ec.notas_adicionales,
                pp.nombre as candidato_nombre,
                pp.correo as candidato_correo,
                pp.telefono as candidato_telefono,
                nc.Nombre as cargo_nombre,
                CONCAT(o.Nombre, ' ', o.Apellido) as entrevistador_nombre,
                (SELECT id FROM postulacion_evaluacion_rh WHERE id_postulacion = ec.id_postulacion) as rh_eval_id,
                (SELECT veredicto FROM postulacion_evaluacion_rh WHERE id_postulacion = ec.id_postulacion) as rh_veredicto,
                (SELECT id FROM postulacion_evaluacion_jefe WHERE id_postulacion = ec.id_postulacion) as jefe_eval_id
            FROM entrevistas_candidatos ec
            INNER JOIN postulacion_plaza pp ON ec.id_postulacion = pp.id
            INNER JOIN NivelesCargos nc ON pp.cargo_aplicado = nc.CodNivelesCargos
            INNER JOIN Operarios o ON ec.reclutador_entrevista = o.CodOperario
            $where
            ORDER BY ec.fecha_entrevista, ec.hora_entrevista";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $entrevistas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'entrevistas' => $entrevistas
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
