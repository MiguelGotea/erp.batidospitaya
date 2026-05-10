<?php
// postulacion_get_jefe_inmediato.php

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $idPostulacion = (int) ($_GET['id'] ?? 0);

    if ($idPostulacion <= 0) {
        throw new Exception('ID de postulación inválido');
    }

    // 1. Obtener el cargo al que aplica el candidato
    $sqlCargo = "SELECT cargo_aplicado, sucursal_aplicada FROM postulacion_plaza WHERE id = :id";
    $stmtCargo = $conn->prepare($sqlCargo);
    $stmtCargo->bindValue(':id', $idPostulacion, PDO::PARAM_INT);
    $stmtCargo->execute();
    $postulacion = $stmtCargo->fetch(PDO::FETCH_ASSOC);

    if (!$postulacion) {
        throw new Exception('Postulación no encontrada');
    }

    $cargoAplicado = $postulacion['cargo_aplicado'];
    $sucursalAplicada = $postulacion['sucursal_aplicada'];

    // 2. Obtener quién es el jefe inmediato (ReportaA) para ese cargo
    $sqlJefe = "SELECT ReportaA FROM NivelesCargos WHERE CodNivelesCargos = :cod";
    $stmtJefe = $conn->prepare($sqlJefe);
    $stmtJefe->bindValue(':cod', $cargoAplicado, PDO::PARAM_INT);
    $stmtJefe->execute();
    $cargoJefe = $stmtJefe->fetchColumn();

    if (!$cargoJefe) {
        throw new Exception('No se encontró un jefe definido para este cargo');
    }

    // 3. Buscar el operario activo en ese cargo de jefe, preferiblemente en la misma sucursal
    // O si es un cargo general (ej: Gerencia de Area) que reporta a él.
    // Usamos AsignacionNivelesCargos para ver quién está activo
    $sqlOperario = "SELECT o.CodOperario, CONCAT(o.Nombre, ' ', o.Apellido) as nombre_completo, nc.Nombre as cargo_nombre
                    FROM AsignacionNivelesCargos anc
                    INNER JOIN Operarios o ON anc.CodOperario = o.CodOperario
                    INNER JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                    WHERE anc.CodNivelesCargos = :cargoJefe 
                    AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                    AND anc.Fecha <= CURDATE()
                    AND (anc.Sucursal = :sucursal1 OR :sucursal2 IS NULL OR nc.Area = 'ADMINISTRATIVA')
                    ORDER BY (anc.Sucursal = :sucursal3) DESC, anc.Fecha DESC
                    LIMIT 1";

    $stmtOperario = $conn->prepare($sqlOperario);
    $stmtOperario->bindValue(':cargoJefe', $cargoJefe, PDO::PARAM_INT);
    $stmtOperario->bindValue(':sucursal1', $sucursalAplicada);
    $stmtOperario->bindValue(':sucursal2', $sucursalAplicada);
    $stmtOperario->bindValue(':sucursal3', $sucursalAplicada);
    $stmtOperario->execute();
    $jefe = $stmtOperario->fetch(PDO::FETCH_ASSOC);

    if (!$jefe) {
        // Si no hay nadie específico, traemos la lista de operarios con ese cargo de jefe para que elijan
        $sqlTodos = "SELECT o.CodOperario, CONCAT(o.Nombre, ' ', o.Apellido) as nombre_completo, nc.Nombre as cargo_nombre
                     FROM AsignacionNivelesCargos anc
                     INNER JOIN Operarios o ON anc.CodOperario = o.CodOperario
                     INNER JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                     WHERE anc.CodNivelesCargos = :cargoJefe 
                     AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                     LIMIT 10";
        $stmtTodos = $conn->prepare($sqlTodos);
        $stmtTodos->bindValue(':cargoJefe', $cargoJefe, PDO::PARAM_INT);
        $stmtTodos->execute();
        $listaJefes = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'jefe_automatico' => false,
            'datos' => $listaJefes,
            'message' => 'No se encontró un jefe activo específico para la sucursal, elija de la lista.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'jefe_automatico' => true,
            'datos' => [$jefe],
            'message' => 'Jefe inmediato identificado correctamente.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
