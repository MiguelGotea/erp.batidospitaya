<?php
// postulacion_panel_control_get_sucursales.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Obtener todas las sucursales activas
    $sql = "SELECT 
                s.codigo as codigo_sucursal,
                s.nombre as nombre_sucursal
            FROM sucursales s
            WHERE s.activa = 1 AND s.sucursal = 1
            ORDER BY s.nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- GLOBAL PDFS (Desde sucursales activas) ---
    $gruposVendedores = [2, 44, 45, 46, 47];
    $gruposLideres = [5, 43];

    // Vendedores
    $sqlGlobalVendedor = "SELECT pc.id as config_id, pc.ruta_pdf_cargo, pc.ruta_banner 
                          FROM plazas_cargos pc
                          INNER JOIN sucursales s ON pc.sucursal = s.codigo
                          WHERE s.activa = 1 AND s.sucursal = 1 AND pc.area = 'Sucursales' 
                          AND pc.cargo IN (" . implode(",", $gruposVendedores) . ") 
                          AND pc.ruta_pdf_cargo IS NOT NULL AND pc.ruta_pdf_cargo != ''
                          LIMIT 1";
    $stmtGlobalVendedor = $conn->prepare($sqlGlobalVendedor);
    $stmtGlobalVendedor->execute();
    $globalVendedor = $stmtGlobalVendedor->fetch(PDO::FETCH_ASSOC);

    // Líderes
    $sqlGlobalLider = "SELECT pc.id as config_id, pc.ruta_pdf_cargo, pc.ruta_banner 
                       FROM plazas_cargos pc
                       INNER JOIN sucursales s ON pc.sucursal = s.codigo
                       WHERE s.activa = 1 AND s.sucursal = 1 AND pc.area = 'Sucursales' 
                       AND pc.cargo IN (" . implode(",", $gruposLideres) . ") 
                       AND pc.ruta_pdf_cargo IS NOT NULL AND pc.ruta_pdf_cargo != ''
                       LIMIT 1";
    $stmtGlobalLider = $conn->prepare($sqlGlobalLider);
    $stmtGlobalLider->execute();
    $globalLider = $stmtGlobalLider->fetch(PDO::FETCH_ASSOC);

    // Para cada sucursal, obtener configuración de vendedores y líderes
    foreach ($sucursales as &$sucursal) {
        $codigoSucursal = $sucursal['codigo_sucursal'];

        // --- GRUPO VENDEDORES ---
        $sqlVendedor = "SELECT id as config_id, cantidad_real, cantidad_adicional, obligatorio, visible_web, salario_propuesto, COALESCE(nivel_urgencia, 1) as nivel_urgencia, ruta_pdf_cargo, ruta_banner
                        FROM plazas_cargos 
                        WHERE sucursal = :sucursal 
                        AND area = 'Sucursales'
                        AND cargo IN (" . implode(",", $gruposVendedores) . ")
                        LIMIT 1";
        $stmtVendedor = $conn->prepare($sqlVendedor);
        $stmtVendedor->bindValue(':sucursal', $codigoSucursal);
        $stmtVendedor->execute();
        $vendedor = $stmtVendedor->fetch(PDO::FETCH_ASSOC);

        $sucursal['vendedor_id'] = $vendedor ? $vendedor['config_id'] : 0;
        $sucursal['vendedor_oblig'] = $vendedor ? $vendedor['cantidad_real'] : 0;
        $sucursal['vendedor_adic'] = $vendedor ? $vendedor['cantidad_adicional'] : 0;
        $sucursal['vendedor_web'] = $vendedor ? $vendedor['visible_web'] : 0;
        $sucursal['vendedor_salario'] = $vendedor ? $vendedor['salario_propuesto'] : 0;
        $sucursal['vendedor_urgencia'] = $vendedor ? ($vendedor['nivel_urgencia'] ?? 1) : 1;
        $sucursal['vendedor_pdf'] = $vendedor ? $vendedor['ruta_pdf_cargo'] : '';
        $sucursal['vendedor_banner'] = $vendedor ? $vendedor['ruta_banner'] : '';

        // Vendedores Cubiertos
        $sqlCubVendedor = "SELECT COUNT(DISTINCT anc.CodOperario) as total
                           FROM AsignacionNivelesCargos anc
                           INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                           WHERE anc.CodNivelesCargos IN (" . implode(",", $gruposVendedores) . ")
                           AND anc.Sucursal = :sucursal
                           AND anc.Fecha <= CURDATE()
                           AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                           AND c.Finalizado = 0";
        $stmtCubVendedor = $conn->prepare($sqlCubVendedor);
        $stmtCubVendedor->bindValue(':sucursal', $codigoSucursal);
        $stmtCubVendedor->execute();
        $cubVendedor = $stmtCubVendedor->fetch(PDO::FETCH_ASSOC);
        $sucursal['vendedor_cubierto'] = $cubVendedor ? $cubVendedor['total'] : 0;

        // --- GRUPO LÍDERES ---
        $sqlLider = "SELECT id as config_id, cantidad_real, cantidad_adicional, obligatorio, visible_web, salario_propuesto, COALESCE(nivel_urgencia, 1) as nivel_urgencia, ruta_pdf_cargo, ruta_banner
                     FROM plazas_cargos 
                     WHERE sucursal = :sucursal 
                     AND area = 'Sucursales'
                     AND cargo IN (" . implode(",", $gruposLideres) . ")
                     LIMIT 1";
        $stmtLider = $conn->prepare($sqlLider);
        $stmtLider->bindValue(':sucursal', $codigoSucursal);
        $stmtLider->execute();
        $lider = $stmtLider->fetch(PDO::FETCH_ASSOC);

        $sucursal['lider_id'] = $lider ? $lider['config_id'] : 0;
        $sucursal['lider_oblig'] = $lider ? $lider['cantidad_real'] : 1; // Por defecto 1
        $sucursal['lider_adic'] = $lider ? $lider['cantidad_adicional'] : 0;
        $sucursal['lider_web'] = $lider ? $lider['visible_web'] : 0;
        $sucursal['lider_salario'] = $lider ? $lider['salario_propuesto'] : 0;
        $sucursal['lider_urgencia'] = $lider ? ($lider['nivel_urgencia'] ?? 1) : 1;
        $sucursal['lider_pdf'] = $lider ? $lider['ruta_pdf_cargo'] : '';
        $sucursal['lider_banner'] = $lider ? $lider['ruta_banner'] : '';



        // Líderes Cubiertos
        $sqlCubLider = "SELECT COUNT(DISTINCT anc.CodOperario) as total
                        FROM AsignacionNivelesCargos anc
                        INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                        WHERE anc.CodNivelesCargos IN (" . implode(",", $gruposLideres) . ")
                        AND anc.Sucursal = :sucursal
                        AND anc.Fecha <= CURDATE()
                        AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                        AND c.Finalizado = 0";
        $stmtCubLider = $conn->prepare($sqlCubLider);
        $stmtCubLider->bindValue(':sucursal', $codigoSucursal);
        $stmtCubLider->execute();
        $cubLider = $stmtCubLider->fetch(PDO::FETCH_ASSOC);
        $sucursal['lider_cubierto'] = $cubLider ? $cubLider['total'] : 0;

        // --- ESPECIALIDADES desde NivelesCargos ---
        $sqlEspVend = "SELECT especialidad_area FROM NivelesCargos
                       WHERE CodNivelesCargos IN (" . implode(',', $gruposVendedores) . ")
                         AND especialidad_area IS NOT NULL AND especialidad_area != ''
                       LIMIT 1";
        $stmtEspVend = $conn->prepare($sqlEspVend);
        $stmtEspVend->execute();
        $espVend = $stmtEspVend->fetchColumn();
        $sucursal['vendedor_especialidad'] = $espVend ?: '';

        $sqlEspLider = "SELECT especialidad_area FROM NivelesCargos
                        WHERE CodNivelesCargos IN (" . implode(',', $gruposLideres) . ")
                          AND especialidad_area IS NOT NULL AND especialidad_area != ''
                        LIMIT 1";
        $stmtEspLider = $conn->prepare($sqlEspLider);
        $stmtEspLider->execute();
        $espLider = $stmtEspLider->fetchColumn();
        $sucursal['lider_especialidad'] = $espLider ?: '';
    }

    echo json_encode([
        'success' => true,
        'datos' => $sucursales,
        'global_pdf' => [
            'vendedor' => [
                'ruta' => $globalVendedor ? $globalVendedor['ruta_pdf_cargo'] : '',
                'banner' => $globalVendedor ? $globalVendedor['ruta_banner'] : '',
                'id' => $globalVendedor ? $globalVendedor['config_id'] : 0
            ],
            'lider' => [
                'ruta' => $globalLider ? $globalLider['ruta_pdf_cargo'] : '',
                'banner' => $globalLider ? $globalLider['ruta_banner'] : '',
                'id' => $globalLider ? $globalLider['config_id'] : 0
            ]
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
