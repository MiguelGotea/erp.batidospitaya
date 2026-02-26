<?php
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    verificarAutenticacion();

    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';

    $opciones = [];

    // Filtro de estado (Operativo)
    if ($columna === 'Operativo') {
        $opciones = [
            ['valor' => '1', 'texto' => 'Activo'],
            ['valor' => '0', 'texto' => 'Inactivo']
        ];
    }

    // Filtro de cargo
    elseif ($columna === 'cargo_nombre') {
        $sql = "SELECT DISTINCT 
                    COALESCE(
                        (SELECT nc.Nombre 
                         FROM AsignacionNivelesCargos anc
                         JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                         WHERE anc.CodOperario = o.CodOperario 
                         AND anc.CodNivelesCargos != 2
                         AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                         ORDER BY anc.CodNivelesCargos DESC
                         LIMIT 1),
                        (SELECT nc.Nombre 
                         FROM AsignacionNivelesCargos anc
                         JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                         WHERE anc.CodOperario = o.CodOperario 
                         AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                         ORDER BY anc.CodNivelesCargos DESC
                         LIMIT 1),
                        'Sin cargo definido'
                    ) as cargo
                FROM Operarios o
                ORDER BY cargo";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $resultados = $stmt->fetchAll();

        foreach ($resultados as $row) {
            $opciones[] = [
                'valor' => $row['cargo'],
                'texto' => $row['cargo']
            ];
        }
    }

    // Filtro de sucursal contrato
    elseif ($columna === 'nombre_sucursal') {
        $sql = "SELECT DISTINCT 
                    COALESCE(s.nombre, 'Sin tienda') as nombre_sucursal
                FROM Operarios o
                LEFT JOIN (
                    SELECT cod_operario, cod_sucursal_contrato
                    FROM Contratos c1
                    WHERE c1.CodContrato = (
                        SELECT CodContrato 
                        FROM Contratos c2 
                        WHERE c2.cod_operario = c1.cod_operario 
                        ORDER BY CodContrato DESC 
                        LIMIT 1
                    )
                ) ultimo_contrato ON ultimo_contrato.cod_operario = o.CodOperario
                LEFT JOIN sucursales s ON ultimo_contrato.cod_sucursal_contrato = s.codigo
                ORDER BY nombre_sucursal";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $resultados = $stmt->fetchAll();

        foreach ($resultados as $row) {
            $opciones[] = [
                'valor' => $row['nombre_sucursal'],
                'texto' => $row['nombre_sucursal']
            ];
        }
    }

    // Filtro de sucursal actual
    elseif ($columna === 'sucursal_actual_nombre') {
        $sql = "SELECT DISTINCT 
                    COALESCE(s.nombre, 'Sin tienda') as sucursal_actual
                FROM Operarios o
                LEFT JOIN (
                    SELECT anc1.CodOperario, anc1.Sucursal
                    FROM AsignacionNivelesCargos anc1
                    WHERE anc1.CodAsignacionNivelesCargos = (
                        SELECT CodAsignacionNivelesCargos 
                        FROM AsignacionNivelesCargos anc2 
                        WHERE anc2.CodOperario = anc1.CodOperario 
                        AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
                        ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC 
                        LIMIT 1
                    )
                ) asignacion_actual ON asignacion_actual.CodOperario = o.CodOperario
                LEFT JOIN sucursales s ON asignacion_actual.Sucursal = s.codigo
                ORDER BY sucursal_actual";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $resultados = $stmt->fetchAll();

        foreach ($resultados as $row) {
            $opciones[] = [
                'valor' => $row['sucursal_actual'],
                'texto' => $row['sucursal_actual']
            ];
        }
    }

    // Filtro de tiempo trabajado (rangos predefinidos)
    elseif ($columna === 'tiempo_trabajado_dias') {
        $opciones = [
            ['valor' => 'menos_6_meses', 'texto' => 'Menos de 6 meses'],
            ['valor' => '6_meses_1_año', 'texto' => '6 meses - 1 año'],
            ['valor' => '1_2_años', 'texto' => '1-2 años'],
            ['valor' => '2_5_años', 'texto' => '2-5 años'],
            ['valor' => 'mas_5_años', 'texto' => 'Más de 5 años']
        ];
    }

    // Filtro de tiempo restante (categorías predefinidas)
    elseif ($columna === 'tiempo_restante_categoria') {
        $opciones = [
            ['valor' => 'vencido', 'texto' => 'Vencido'],
            ['valor' => 'menos_1_mes', 'texto' => 'Menos de 1 mes'],
            ['valor' => '1_3_meses', 'texto' => '1-3 meses'],
            ['valor' => '3_6_meses', 'texto' => '3-6 meses'],
            ['valor' => '6_12_meses', 'texto' => '6-12 meses'],
            ['valor' => 'mas_1_año', 'texto' => 'Más de 1 año'],
            ['valor' => 'indefinido', 'texto' => 'Indefinido']
        ];
    }

    echo json_encode([
        'success' => true,
        'opciones' => $opciones
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>