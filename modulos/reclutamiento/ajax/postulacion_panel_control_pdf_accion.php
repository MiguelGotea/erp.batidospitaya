<?php
// postulacion_panel_control_pdf_accion.php

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/ai/AIService.php';
require_once '../../../core/utils/DocumentParser.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $accion = $_POST['accion'] ?? '';
    $tipo = $_POST['tipo'] ?? 'pdf'; // 'pdf' o 'banner'
    $idConfig = (int) ($_POST['id_config'] ?? 0);
    $cargo = (int) ($_POST['cargo'] ?? 0);
    $sucursal = (int) ($_POST['sucursal'] ?? 0);
    $area = $_POST['area'] ?? '';

    if ($accion === 'subir') {
        if ($tipo === 'pdf') {
            if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al recibir el archivo');
            }
            $file = $_FILES['pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExts)) {
                throw new Exception('Formato de archivo no soportado. Use PDF, Word o Imágenes.');
            }
            $uploadDir = '../uploads/cargos/';
            $columna = 'ruta_pdf_cargo';
            $fileNamePrefix = 'cargo_';
            $fileExt = '.' . $ext;
        } else {
            if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error al recibir la imagen');
            }
            $file = $_FILES['banner'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                throw new Exception('Solo se permiten archivos JPG o PNG');
            }
            $uploadDir = '../uploads/banner_puesto/';
            $columna = 'ruta_banner';
            $fileNamePrefix = 'banner_';
            $fileExt = '.' . $ext;
        }
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = $fileNamePrefix . $cargo . '_suc_' . $sucursal . '_' . time() . $fileExt;
        $uploadPath = $uploadDir . $fileName;

        // Definir grupos de cargos
        $gruposCargos = [
            2 => [2, 44, 45, 46, 47], // Vendedores
            5 => [5, 43]              // Líderes
        ];
        $cargosAProcesar = isset($gruposCargos[$cargo]) ? $gruposCargos[$cargo] : [$cargo];

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            
            // --- INICIO DESTILACION IA ---
            $perfilDestilado = null;
            if ($tipo === 'pdf') {
                try {
                    $aiService = new AIService($conn, 'google');
                    // Preparar archivo para procesamiento
                    $mockFile = [
                        'name' => $file['name'],
                        'type' => $file['type'],
                        'tmp_name' => $uploadPath // Usar la ruta ya subida
                    ];
                    $parsedDoc = DocumentParser::parseDocument($mockFile);
                    $perfilDestilado = DocumentParser::distillProfile(
                        $aiService, 
                        $parsedDoc['content'] ?? '', 
                        ($parsedDoc['type'] === 'inline_data' ? [['inline_data' => ['mime_type' => $parsedDoc['mime_type'], 'data' => $parsedDoc['data']]]] : [])
                    );
                } catch (Exception $e_ia) {
                    error_log("Error en destilación IA: " . $e_ia->getMessage());
                }
            }
            // --- FIN DESTILACION IA ---

            // Si sucursal es 0, es "Global", replicar a todas las sucursales activas
            if ($sucursal === 0) {
                // Obtener todas las sucursales activas
                $sqlSuc = "SELECT codigo FROM sucursales WHERE activa = 1 AND sucursal = 1";
                $stmtSuc = $conn->query($sqlSuc);
                $sucursalesActivas = $stmtSuc->fetchAll(PDO::FETCH_COLUMN);

                foreach ($sucursalesActivas as $codSucursal) {
                    foreach ($cargosAProcesar as $idCargo) {
                        // Verificar si ya existe un registro para esta sucursal y cargo
                        $sqlCheck = "SELECT id FROM plazas_cargos WHERE cargo = :cargo AND sucursal = :sucursal AND area = :area LIMIT 1";
                        $stmtCheck = $conn->prepare($sqlCheck);
                        $stmtCheck->bindValue(':cargo', $idCargo, PDO::PARAM_INT);
                        $stmtCheck->bindValue(':sucursal', $codSucursal);
                        $stmtCheck->bindValue(':area', $area);
                        $stmtCheck->execute();
                        $existing = $stmtCheck->fetch();

                        if ($existing) {
                            // Actualizar
                            if ($tipo === 'banner') {
                                $sqlUpd = "UPDATE plazas_cargos SET ruta_banner = :ruta1, ruta_banner_cargo = :ruta2, usuario_modifica = :usuario, fecha_actualizacion = NOW() WHERE id = :id";
                            } else {
                                $sqlUpd = "UPDATE plazas_cargos SET $columna = :ruta, 
                                                                    perfil_ia_destilado = :perfil, 
                                                                    perfil_ia_ultima_act = NOW(), 
                                                                    usuario_modifica = :usuario, 
                                                                    fecha_actualizacion = NOW() 
                                           WHERE id = :id";
                            }
                            $stmtUpd = $conn->prepare($sqlUpd);
                            if ($tipo === 'banner') {
                                $stmtUpd->bindValue(':ruta1', $fileName);
                                $stmtUpd->bindValue(':ruta2', $fileName);
                            } else {
                                $stmtUpd->bindValue(':ruta', $fileName);
                                $stmtUpd->bindValue(':perfil', $perfilDestilado);
                            }
                            $stmtUpd->bindValue(':usuario', $codOperario, PDO::PARAM_INT);
                            $stmtUpd->bindValue(':id', $existing['id'], PDO::PARAM_INT);
                            $stmtUpd->execute();
                        } else {
                            // Insertar nuevo
                            if ($tipo === 'banner') {
                                $sqlIns = "INSERT INTO plazas_cargos (cargo, sucursal, area, ruta_banner, ruta_banner_cargo, usuario_registra, fecha_creacion, obligatorio, cantidad_real) 
                                           VALUES (:cargo, :sucursal, :area, :ruta1, :ruta2, :usuario, NOW(), 1, :cant_real)";
                            } else {
                                $sqlIns = "INSERT INTO plazas_cargos (cargo, sucursal, area, $columna, perfil_ia_destilado, perfil_ia_ultima_act, usuario_registra, fecha_creacion, obligatorio, cantidad_real) 
                                           VALUES (:cargo, :sucursal, :area, :ruta, :perfil, NOW(), :usuario, NOW(), 1, :cant_real)";
                            }
                            $stmtIns = $conn->prepare($sqlIns);
                            $stmtIns->bindValue(':cargo', $idCargo, PDO::PARAM_INT);
                            $stmtIns->bindValue(':sucursal', $codSucursal);
                            $stmtIns->bindValue(':area', $area);
                            if ($tipo === 'banner') {
                                $stmtIns->bindValue(':ruta1', $fileName);
                                $stmtIns->bindValue(':ruta2', $fileName);
                            } else {
                                $stmtIns->bindValue(':ruta', $fileName);
                                $stmtIns->bindValue(':perfil', $perfilDestilado);
                            }
                            $stmtIns->bindValue(':usuario', $codOperario, PDO::PARAM_INT);
                            $stmtIns->bindValue(':cant_real', ($idCargo == 5 || $idCargo == 43) ? 1 : 0, PDO::PARAM_INT);
                            $stmtIns->execute();
                        }
                    }
                }
            } else {
                // Caso específico para un grupo de cargos en una sucursal específica
                foreach ($cargosAProcesar as $idCargo) {
                    $sqlCheck = "SELECT id FROM plazas_cargos 
                                 WHERE sucursal = :sucursal 
                                 AND cargo = :cargo 
                                 AND area = :area LIMIT 1";
                    $stmtCheck = $conn->prepare($sqlCheck);
                    $stmtCheck->bindValue(':sucursal', $sucursal);
                    $stmtCheck->bindValue(':cargo', $idCargo, PDO::PARAM_INT);
                    $stmtCheck->bindValue(':area', $area);
                    $stmtCheck->execute();
                    $existing = $stmtCheck->fetch();

                    if ($existing) {
                        if ($tipo === 'banner') {
                            $sql = "UPDATE plazas_cargos SET ruta_banner = :ruta1, ruta_banner_cargo = :ruta2, usuario_modifica = :usuario, fecha_actualizacion = NOW() WHERE id = :id";
                        } else {
                            $sql = "UPDATE plazas_cargos SET $columna = :ruta, 
                                                             perfil_ia_destilado = :perfil, 
                                                             perfil_ia_ultima_act = NOW(), 
                                                             usuario_modifica = :usuario, 
                                                             fecha_actualizacion = NOW() 
                                    WHERE id = :id";
                        }
                        $stmt = $conn->prepare($sql);
                        if ($tipo === 'banner') {
                            $stmt->bindValue(':ruta1', $fileName);
                            $stmt->bindValue(':ruta2', $fileName);
                        } else {
                            $stmt->bindValue(':ruta', $fileName);
                            $stmt->bindValue(':perfil', $perfilDestilado);
                        }
                        $stmt->bindValue(':usuario', $codOperario, PDO::PARAM_INT);
                        $stmt->bindValue(':id', $existing['id'], PDO::PARAM_INT);
                        $stmt->execute();
                    } else {
                        if ($tipo === 'banner') {
                            $sql = "INSERT INTO plazas_cargos (cargo, sucursal, area, ruta_banner, ruta_banner_cargo, usuario_registra, fecha_creacion, obligatorio, cantidad_real) 
                                    VALUES (:cargo, :sucursal, :area, :ruta1, :ruta2, :usuario, NOW(), 1, :cant_real)";
                        } else {
                            $sql = "INSERT INTO plazas_cargos (cargo, sucursal, area, $columna, perfil_ia_destilado, perfil_ia_ultima_act, usuario_registra, fecha_creacion, obligatorio, cantidad_real) 
                                    VALUES (:cargo, :sucursal, :area, :ruta, :perfil, NOW(), :usuario, NOW(), 1, :cant_real)";
                        }
                        $stmt = $conn->prepare($sql);
                        $stmt->bindValue(':cargo', $idCargo, PDO::PARAM_INT);
                        $stmt->bindValue(':sucursal', $sucursal);
                        $stmt->bindValue(':area', $area);
                        if ($tipo === 'banner') {
                            $stmt->bindValue(':ruta1', $fileName);
                            $stmt->bindValue(':ruta2', $fileName);
                        } else {
                            $stmt->bindValue(':ruta', $fileName);
                            $stmt->bindValue(':perfil', $perfilDestilado);
                        }
                        $stmt->bindValue(':usuario', $codOperario, PDO::PARAM_INT);
                        $stmt->bindValue(':cant_real', ($idCargo == 5 || $idCargo == 43) ? 1 : 0, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                }
            }

            echo json_encode(['success' => true, 'message' => ($tipo === 'pdf' ? 'Perfil' : 'Banner') . ' subido y procesado correctamente', 'ruta' => $fileName]);
        } else {
            throw new Exception('Error al mover el archivo al servidor');
        }
    } elseif ($accion === 'eliminar') {
        $cargo = (int) ($_POST['cargo'] ?? 0);
        $sucursalReq = $_POST['sucursal'] ?? '';
        $area = $_POST['area'] ?? '';

        $columna = ($tipo === 'pdf') ? 'ruta_pdf_cargo' : 'ruta_banner';
        $setNull = ($tipo === 'banner') ? "ruta_banner = NULL, ruta_banner_cargo = NULL" : "$columna = NULL, perfil_ia_destilado = NULL, perfil_ia_ultima_act = NULL";
        $uploadDir = ($tipo === 'pdf') ? '../uploads/cargos/' : '../uploads/banner_puesto/';

        // Definir grupos de cargos
        $gruposCargos = [
            2 => [2, 44, 45, 46, 47], // Vendedores
            5 => [5, 43]              // Líderes
        ];
        $cargosAProcesar = isset($gruposCargos[$cargo]) ? $gruposCargos[$cargo] : [$cargo];

        if ($sucursalReq === "0" || $sucursalReq === 0) {
            // GLOBAL: Eliminar de todas las sucursales activas
            $sqlSuc = "SELECT codigo FROM sucursales WHERE activa = 1 AND sucursal = 1";
            $stmtSuc = $conn->query($sqlSuc);
            $sucursalesActivas = $stmtSuc->fetchAll(PDO::FETCH_COLUMN);

            // Primero borrar el archivo físico (solo una vez)
            $sqlPath = "SELECT $columna FROM plazas_cargos pc 
                        INNER JOIN sucursales s ON pc.sucursal = s.codigo
                        WHERE s.activa = 1 AND pc.cargo = :cargo AND pc.area = :area AND pc.$columna IS NOT NULL LIMIT 1";
            $stmtPath = $conn->prepare($sqlPath);
            $stmtPath->bindValue(':cargo', $cargo, PDO::PARAM_INT);
            $stmtPath->bindValue(':area', $area);
            $stmtPath->execute();
            $config = $stmtPath->fetch();

            if ($config && $config[$columna]) {
                $filePath = $uploadDir . $config[$columna];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Actualizar todos los cargos del grupo en todas las sucursales activas a NULL
            $sqlUpd = "UPDATE plazas_cargos SET $setNull 
                       WHERE cargo IN (" . implode(",", $cargosAProcesar) . ") 
                       AND area = :area 
                       AND sucursal IN ('" . implode("','", $sucursalesActivas) . "')";
            $stmtUpd = $conn->prepare($sqlUpd);
            $stmtUpd->bindValue(':area', $area);
            $stmtUpd->execute();

            echo json_encode(['success' => true, 'message' => ($tipo === 'pdf' ? 'Perfil' : 'Banner') . ' global eliminado correctamente']);
        } else {
            // ESPECÍFICO pero para el grupo de cargos
            // Obtener ruta para borrar el archivo físico
            $sqlPath = "SELECT $columna FROM plazas_cargos WHERE cargo = :cargo AND sucursal = :sucursal AND area = :area AND $columna IS NOT NULL LIMIT 1";
            $stmtPath = $conn->prepare($sqlPath);
            $stmtPath->bindValue(':cargo', $cargo, PDO::PARAM_INT);
            $stmtPath->bindValue(':sucursal', $sucursalReq);
            $stmtPath->bindValue(':area', $area);
            $stmtPath->execute();
            $config = $stmtPath->fetch();

            if ($config && $config[$columna]) {
                $filePath = $uploadDir . $config[$columna];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $sql = "UPDATE plazas_cargos SET $setNull WHERE cargo IN (" . implode(",", $cargosAProcesar) . ") AND sucursal = :sucursal AND area = :area";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':sucursal', $sucursalReq);
            $stmt->bindValue(':area', $area);
            $stmt->execute();

            echo json_encode(['success' => true, 'message' => ($tipo === 'pdf' ? 'Perfil' : 'Banner') . ' eliminado correctamente']);
        }
    } else {
        throw new Exception('Acción no válida');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>