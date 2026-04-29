<?php
// postulacion_candidatos_plaza_get_datos.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $idPlaza = (int) ($input['id_plaza'] ?? 0);
    $registros_por_pagina = (int) ($input['registros_por_pagina'] ?? 10);

    if ($idPlaza <= 0) {
        throw new Exception('ID de plaza inválido');
    }

    // Obtener información de la plaza para hacer el match
    $sqlPlaza = "SELECT pc.cargo, pc.sucursal, s.cod_departamento 
                 FROM plazas_cargos pc 
                 LEFT JOIN sucursales s ON pc.sucursal = s.codigo
                 WHERE pc.id = :id_plaza";
    $stmtPlaza = $conn->prepare($sqlPlaza);
    $stmtPlaza->bindValue(':id_plaza', $idPlaza, PDO::PARAM_INT);
    $stmtPlaza->execute();
    $plaza = $stmtPlaza->fetch(PDO::FETCH_ASSOC);

    if (!$plaza) {
        throw new Exception('Plaza no encontrada');
    }

    $cargo_id = intval($plaza['cargo']);
    $es_vendedor = in_array($cargo_id, [2, 44, 45, 46, 47]);
    $es_lider = in_array($cargo_id, [5, 43]);

    // Construir la condición WHERE
    $cargos_busqueda = [$cargo_id];
    $sucursales_busqueda = [$plaza['sucursal']];

    if ($es_vendedor || $es_lider) {
        // Para cargos masivos, buscamos todas las sucursales del mismo departamento
        $cargos_busqueda = $es_vendedor ? [2, 44, 45, 46, 47] : [5, 43];
        
        $sqlSucursales = "SELECT codigo FROM sucursales WHERE cod_departamento = :cod_dept";
        $stmtSucs = $conn->prepare($sqlSucursales);
        $stmtSucs->bindValue(':cod_dept', $plaza['cod_departamento']);
        $stmtSucs->execute();
        $sucursales_busqueda = $stmtSucs->fetchAll(PDO::FETCH_COLUMN);
    }

    $cargos_in = implode(',', array_map('intval', $cargos_busqueda));
    $sucursales_in = implode("','", array_map('strval', $sucursales_busqueda));

    // Obtener postulaciones para esta plaza con sus respectivos estados de evaluación
    $sql = "SELECT 
                pp.id,
                pp.nombre,
                pp.correo,
                pp.telefono,
                pp.ruta_cv,
                pp.fecha_postulacion,
                pp.status,
                pp.analisis_ia,
                pp.experiencia_laboral,
                pp.direccion,
                (SELECT AVG(confianza) 
                 FROM validacion_cv_ia 
                 WHERE id_postulacion = pp.id) as match_porcentaje,
                IF(erh.id IS NOT NULL, 1, 0) as has_rh_eval,
                erh.puntaje_acumulado as rh_score,
                erh.veredicto as rh_veredicto,
                IF(eja.id IS NOT NULL, 1, 0) as has_jefe_eval,
                eja.promedio_estrellas as jefe_score,
                eja.veredicto as jefe_veredicto,
                se.token as solicitud_token,
                COALESCE(se.codigo_acceso, '') as codigo_acceso,
                COALESCE(se.link_status, 'activo') as link_status,
                se.porcentaje_completitud
            FROM postulacion_plaza pp
            LEFT JOIN postulacion_evaluacion_rh erh ON pp.id = erh.id_postulacion
            LEFT JOIN postulacion_evaluacion_jefe eja ON pp.id = eja.id_postulacion
            LEFT JOIN solicitud_empleo se ON pp.id = se.id_postulacion
            WHERE pp.cargo_aplicado IN ($cargos_in)
            AND pp.sucursal_aplicada IN ('$sucursales_in')
            ORDER BY pp.fecha_postulacion DESC
            LIMIT :limit";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agregar prefijo a las rutas de CV y extraer años de experiencia
    foreach ($datos as &$candidato) {
        // Agregar prefijo a ruta_cv
        if ($candidato['ruta_cv']) {
            $candidato['ruta_cv'] = 'https://talento.batidospitaya.com/uploads/' . $candidato['ruta_cv'];
        }

        // Extraer años de experiencia del texto
        $candidato['experiencia_anos'] = null;
        if ($candidato['experiencia_laboral']) {
            // Buscar patrones como "3 años", "5 años de experiencia", etc.
            if (preg_match('/(\d+)\s*a[ñn]os?/i', $candidato['experiencia_laboral'], $matches)) {
                $candidato['experiencia_anos'] = (int) $matches[1];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'datos' => $datos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>