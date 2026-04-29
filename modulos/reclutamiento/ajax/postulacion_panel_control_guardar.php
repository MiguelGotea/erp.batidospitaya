<?php
// postulacion_panel_control_guardar.php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    // Recibir datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $sucursales = $input['sucursales'] ?? [];
    $cargos = $input['cargos'] ?? [];

    $conn->beginTransaction();

    // ========================================
    // PROCESAR DATOS DE SUCURSALES (Vendedores y Líderes)
    // ========================================
    $gruposCargos = [
        2 => [2], // Vendedores (Solo CodNivelesCargos 2)
        5 => [5]  // Líderes (Solo CodNivelesCargos 5)
    ];

    foreach ($sucursales as $dato) {
        $codigoSucursal = $dato['sucursal'];
        $codCargoBase = (int) $dato['cargo'];
        $cantidadRealBase = (int) $dato['cantidad_real'];
        $cantidadAdicional = (int) $dato['cantidad_adicional'];
        $obligatorio = 1; // Siempre 1 según requerimiento
        $visibleWeb = (int) $dato['visible_web'];
        $salarioPropuesto = (float) $dato['salario_propuesto'];
        $nivelUrgencia = 4; // Sucursales siempre es Crítico (4)

        $cargosAProcesar = isset($gruposCargos[$codCargoBase]) ? $gruposCargos[$codCargoBase] : [$codCargoBase];

        foreach ($cargosAProcesar as $codCargo) {
            // Para Líderes (5 y 43), cantidad_real es 1 por defecto
            $cantidadReal = ($codCargo == 5 || $codCargo == 43) ? 1 : $cantidadRealBase;

            // Verificar si existe el registro
            $sqlCheck = "SELECT id FROM plazas_cargos 
                         WHERE sucursal = :sucursal 
                         AND cargo = :cargo
                         AND area = 'Sucursales'";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->bindValue(':sucursal', $codigoSucursal);
            $stmtCheck->bindValue(':cargo', $codCargo, PDO::PARAM_INT);
            $stmtCheck->execute();
            $existe = $stmtCheck->fetch();

            if ($existe) {
                // Actualizar
                $sqlUpdate = "UPDATE plazas_cargos 
                              SET cantidad_real = :cantidad_real,
                                  cantidad_adicional = :cantidad_adicional,
                                  obligatorio = :obligatorio,
                                  visible_web = :visible_web,
                                  salario_propuesto = :salario_propuesto,
                                  nivel_urgencia = :nivel_urgencia,
                                  usuario_modifica = :usuario_modifica,
                                  fecha_actualizacion = NOW()
                              WHERE id = :id";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bindValue(':cantidad_real', $cantidadReal, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':cantidad_adicional', $cantidadAdicional, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':visible_web', $visibleWeb, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':salario_propuesto', $salarioPropuesto);
                $stmtUpdate->bindValue(':nivel_urgencia', $nivelUrgencia, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':usuario_modifica', $codOperario, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':id', $existe['id'], PDO::PARAM_INT);
                $stmtUpdate->execute();
            } else {
                // Insertar
                $sqlInsert = "INSERT INTO plazas_cargos 
                              (cargo, cantidad_real, cantidad_adicional, sucursal, area, obligatorio, visible_web, salario_propuesto, nivel_urgencia, usuario_registra, fecha_creacion)
                              VALUES (:cargo, :cantidad_real, :cantidad_adicional, :sucursal, 'Sucursales', :obligatorio, :visible_web, :salario_propuesto, :nivel_urgencia, :usuario_registra, NOW())";
                $stmtInsert = $conn->prepare($sqlInsert);
                $stmtInsert->bindValue(':cargo', $codCargo, PDO::PARAM_INT);
                $stmtInsert->bindValue(':cantidad_real', $cantidadReal, PDO::PARAM_INT);
                $stmtInsert->bindValue(':cantidad_adicional', $cantidadAdicional, PDO::PARAM_INT);
                $stmtInsert->bindValue(':sucursal', $codigoSucursal);
                $stmtInsert->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
                $stmtInsert->bindValue(':visible_web', $visibleWeb, PDO::PARAM_INT);
                $stmtInsert->bindValue(':salario_propuesto', $salarioPropuesto);
                $stmtInsert->bindValue(':nivel_urgencia', $nivelUrgencia, PDO::PARAM_INT);
                $stmtInsert->bindValue(':usuario_registra', $codOperario, PDO::PARAM_INT);
                $stmtInsert->execute();
            }
        }
    }

    // ========================================
    // PROCESAR DATOS DE CARGOS (Administrativo y Producción)
    // ========================================
    foreach ($cargos as $dato) {
        $codCargo = (int) $dato['cargo'];
        $area = $dato['area']; // 'Administrativo' o 'Produccion'
        $sucursal = (int) $dato['sucursal']; // 18 para Administrativo, 6 para Producción
        $cantidadReal = isset($dato['cantidad_real']) ? (int) $dato['cantidad_real'] : 0;
        $cantidadAdicional = isset($dato['cantidad_adicional']) ? (int) $dato['cantidad_adicional'] : 0;
        $obligatorio = 1; // Siempre 1 según requerimiento
        $visibleWeb = isset($dato['visible_web']) ? (int) $dato['visible_web'] : 0;
        $salarioPropuesto = isset($dato['salario_propuesto']) ? (float) $dato['salario_propuesto'] : 0;
        $nivelUrgencia = isset($dato['nivel_urgencia']) ? (int) $dato['nivel_urgencia'] : 1;

        // Verificar si existe el registro
        $sqlCheck = "SELECT id FROM plazas_cargos 
                     WHERE cargo = :cargo 
                     AND area = :area
                     AND sucursal = :sucursal";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindValue(':cargo', $codCargo, PDO::PARAM_INT);
        $stmtCheck->bindValue(':area', $area);
        $stmtCheck->bindValue(':sucursal', $sucursal, PDO::PARAM_INT);
        $stmtCheck->execute();
        $existe = $stmtCheck->fetch();

        if ($existe) {
            // Actualizar
            $sqlUpdate = "UPDATE plazas_cargos 
                          SET cantidad_real = :cantidad_real,
                              cantidad_adicional = :cantidad_adicional,
                              obligatorio = :obligatorio,
                              visible_web = :visible_web,
                              salario_propuesto = :salario_propuesto,
                              nivel_urgencia = :nivel_urgencia,
                              usuario_modifica = :usuario_modifica,
                              fecha_actualizacion = NOW()
                          WHERE id = :id";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bindValue(':cantidad_real', $cantidadReal, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':cantidad_adicional', $cantidadAdicional, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':visible_web', $visibleWeb, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':salario_propuesto', $salarioPropuesto);
            $stmtUpdate->bindValue(':nivel_urgencia', $nivelUrgencia, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':usuario_modifica', $codOperario, PDO::PARAM_INT);
            $stmtUpdate->bindValue(':id', $existe['id'], PDO::PARAM_INT);
            $stmtUpdate->execute();
        } else {
            // Insertar
            $sqlInsert = "INSERT INTO plazas_cargos 
                          (cargo, cantidad_real, cantidad_adicional, obligatorio, visible_web, salario_propuesto, nivel_urgencia, sucursal, area, usuario_registra, fecha_creacion)
                          VALUES (:cargo, :cantidad_real, :cantidad_adicional, :obligatorio, :visible_web, :salario_propuesto, :nivel_urgencia, :sucursal, :area, :usuario_registra, NOW())";
            $stmtInsert = $conn->prepare($sqlInsert);
            $stmtInsert->bindValue(':cargo', $codCargo, PDO::PARAM_INT);
            $stmtInsert->bindValue(':cantidad_real', $cantidadReal, PDO::PARAM_INT);
            $stmtInsert->bindValue(':cantidad_adicional', $cantidadAdicional, PDO::PARAM_INT);
            $stmtInsert->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
            $stmtInsert->bindValue(':visible_web', $visibleWeb, PDO::PARAM_INT);
            $stmtInsert->bindValue(':salario_propuesto', $salarioPropuesto);
            $stmtInsert->bindValue(':nivel_urgencia', $nivelUrgencia, PDO::PARAM_INT);
            $stmtInsert->bindValue(':sucursal', $sucursal, PDO::PARAM_INT);
            $stmtInsert->bindValue(':area', $area);
            $stmtInsert->bindValue(':usuario_registra', $codOperario, PDO::PARAM_INT);
            $stmtInsert->execute();
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Configuración guardada exitosamente'
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
