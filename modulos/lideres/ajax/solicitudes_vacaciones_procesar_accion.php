<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// ajax/solicitudes_vacaciones_procesar_accion.php
// Este archivo contiene las funciones de procesamiento que se llaman desde solicitudes_vacaciones.php

/**
 * Procesa las acciones de aprobación/rechazo y nueva solicitud
 */
function procesarAccionSolicitud() {
    global $conn, $esCargo11, $esCargo13, $esCargo28;
    
    $accion = $_POST['accion'] ?? '';
    
    // Para nueva solicitud, no requerimos id_solicitud
    if ($accion === 'nueva_solicitud') {
        procesarNuevaSolicitud();
        return;
    }
    
    // Para otras acciones, sí necesitamos id_solicitud
    $idSolicitud = (int)($_POST['id_solicitud'] ?? 0);
    
    if (!$idSolicitud || !$accion) {
        $_SESSION['error'] = 'Datos incompletos para esta acción';
        return;
    }
    
    // Verificar permisos
    if (!puedeAprobarSolicitud($idSolicitud, $_SESSION['usuario_id'])) {
        $_SESSION['error'] = 'No tiene permisos para realizar esta acción';
        return;
    }
    
    switch ($accion) {
        case 'aprobar_operaciones':
            if (!$esCargo11) {
                $_SESSION['error'] = 'Solo el cargo 11 puede aprobar en operaciones';
                return;
            }
            
            if (aprobarSolicitudOperaciones($idSolicitud, $_SESSION['usuario_id'])) {
                $_SESSION['exito'] = 'Solicitud aprobada por operaciones';
            } else {
                $_SESSION['error'] = 'Error al aprobar la solicitud';
            }
            break;
            
        case 'aprobar_rh':
            if (!$esCargo13 && !$esCargo28) {
                $_SESSION['error'] = 'Solo RH puede aprobar definitivamente';
                return;
            }
            
            $resultado = aprobarSolicitudRH($idSolicitud, $_SESSION['usuario_id']);
            if ($resultado !== false) {
                $_SESSION['exito'] = "Solicitud aprobada y $resultado días registrados en faltas";
            } else {
                $_SESSION['error'] = 'Error al aprobar la solicitud';
            }
            break;
            
        case 'rechazar':
            if (rechazarSolicitud($idSolicitud, $_SESSION['usuario_id'])) {
                $_SESSION['exito'] = 'Solicitud rechazada';
            } else {
                $_SESSION['error'] = 'Error al rechazar la solicitud';
            }
            break;
    }
}

/**
 * Procesa nueva solicitud de vacaciones
 */
function procesarNuevaSolicitud() {
    global $conn;
    
    try {
        // Validar campos obligatorios
        if (empty($_POST['cod_operario'])) {
            throw new Exception("Debe seleccionar un colaborador");
        }
        
        if (empty($_POST['fecha_inicio'])) {
            throw new Exception("La fecha de inicio es obligatoria");
        }
        
        if (empty($_POST['fecha_fin'])) {
            throw new Exception("La fecha fin es obligatoria");
        }
        
        // Validar fechas
        if ($_POST['fecha_inicio'] > $_POST['fecha_fin']) {
            throw new Exception('La fecha de inicio no puede ser mayor que la fecha fin');
        }
        
        // Validar que la fecha no sea pasada
        $hoy = date('Y-m-d');
        if ($_POST['fecha_inicio'] < $hoy) {
            throw new Exception('No puede solicitar vacaciones para fechas pasadas');
        }
        
        // Obtener código de contrato
        $codContrato = obtenerUltimoCodigoContrato($_POST['cod_operario']);
        
        // Obtener sucursal del usuario actual
        $sucursalesUsuario = obtenerSucursalesUsuario($_SESSION['usuario_id']);
        
        if (empty($sucursalesUsuario)) {
            throw new Exception('No tiene una sucursal asignada');
        }
        
        $codSucursal = $sucursalesUsuario[0]['codigo'];
        
        // Procesar foto si se subió
        $fotoPath = null;
        if (isset($_FILES['foto_soporte']) && $_FILES['foto_soporte']['error'] === UPLOAD_ERR_OK) {
            $foto = $_FILES['foto_soporte'];
            
            // Validar tamaño (máximo 5MB)
            if ($foto['size'] > 5 * 1024 * 1024) {
                throw new Exception('La foto no debe exceder los 5MB');
            }
            
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($foto['type'], $allowedTypes)) {
                throw new Exception('Solo se permiten imágenes JPEG, PNG o GIF');
            }
            
            // Crear nombre único para el archivo
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nombreFoto = 'vacacion_' . $_POST['cod_operario'] . '_' . date('YmdHis') . '.' . $extension;
            $rutaRelativa = '/uploads/solicitudes_vacaciones/' . $nombreFoto;
            $uploadDir = __DIR__ . '/../../uploads/solicitudes_vacaciones/';
            
            // Crear directorio si no existe
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $rutaCompleta = $uploadDir . $nombreFoto;
            
            // Mover el archivo
            if (!move_uploaded_file($foto['tmp_name'], $rutaCompleta)) {
                throw new Exception('Error al guardar la foto');
            }
            
            $fotoPath = $rutaRelativa;
        }
        
        // Preparar datos
        $datos = [
            'cod_operario' => (int)$_POST['cod_operario'],
            'fecha_inicio' => $_POST['fecha_inicio'],
            'fecha_fin' => $_POST['fecha_fin'],
            'cod_sucursal' => $codSucursal,
            'tipo_solicitud' => 'Vacaciones',
            'observaciones' => $_POST['observaciones'] ?? '',
            'foto_soporte' => $fotoPath,
            'solicitado_por' => $_SESSION['usuario_id'],
            'cod_contrato' => $codContrato,
            'porcentaje_pago' => 100
        ];
        
        // Crear solicitud
        $idSolicitud = crearSolicitudVacaciones($datos);
        
        if ($idSolicitud) {
            $_SESSION['exito'] = 'Solicitud de vacaciones creada exitosamente';
        } else {
            throw new Exception('Error al crear la solicitud en la base de datos');
        }
        
    } catch (Exception $e) {
        // Eliminar foto si hubo error
        if (isset($rutaCompleta) && file_exists($rutaCompleta)) {
            @unlink($rutaCompleta);
        }
        $_SESSION['error'] = 'Error al crear solicitud: ' . $e->getMessage();
    }
}

/**
 * Crea una nueva solicitud de vacaciones
 */
function crearSolicitudVacaciones($datos) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO solicitudes_vacaciones (
                cod_operario, fecha_inicio, fecha_fin, cod_sucursal,
                tipo_solicitud, observaciones, foto_soporte,
                solicitado_por, cod_contrato, porcentaje_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $datos['cod_operario'],
            $datos['fecha_inicio'],
            $datos['fecha_fin'],
            $datos['cod_sucursal'],
            $datos['tipo_solicitud'] ?? 'Vacaciones',
            $datos['observaciones'] ?? '',
            $datos['foto_soporte'] ?? null,
            $datos['solicitado_por'],
            $datos['cod_contrato'] ?? null,
            $datos['porcentaje_pago'] ?? 100
        ]);
        
        return $result ? $conn->lastInsertId() : false;
        
    } catch (PDOException $e) {
        error_log("Error creando solicitud vacaciones: " . $e->getMessage());
        return false;
    }
}

/**
 * Aprueba una solicitud de vacaciones por operaciones
 */
function aprobarSolicitudOperaciones($idSolicitud, $codAprobador) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE solicitudes_vacaciones 
            SET estado = 'Aprobado_Operaciones',
                aprobado_operaciones_por = ?,
                fecha_aprobacion_operaciones = NOW()
            WHERE id = ?
            AND estado = 'Pendiente'
        ");
        
        return $stmt->execute([$codAprobador, $idSolicitud]);
        
    } catch (PDOException $e) {
        error_log("Error aprobando solicitud operaciones: " . $e->getMessage());
        return false;
    }
}

/**
 * Aprueba una solicitud de vacaciones por RH
 */
function aprobarSolicitudRH($idSolicitud, $codAprobador) {
    global $conn;
    
    try {
        $conn->beginTransaction();
        
        // 1. Obtener los datos de la solicitud
        $stmt = $conn->prepare("
            SELECT * FROM solicitudes_vacaciones 
            WHERE id = ? 
            AND estado IN ('Aprobado_Operaciones', 'Pendiente')
        ");
        $stmt->execute([$idSolicitud]);
        $solicitud = $stmt->fetch();
        
        if (!$solicitud) {
            throw new Exception("Solicitud no encontrada o no aprobada por operaciones");
        }
        
        // 2. Actualizar estado
        $stmt = $conn->prepare("
            UPDATE solicitudes_vacaciones 
            SET estado = 'Aprobado_RH',
                aprobado_rh_por = ?,
                fecha_aprobacion_rh = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$codAprobador, $idSolicitud]);
        
        // 3. Copiar a faltas_manual por cada día del rango
        $diasVacacion = obtenerTodosDiasEnRango($solicitud['fecha_inicio'], $solicitud['fecha_fin']);
        
        $registrosCreados = 0;
        foreach ($diasVacacion as $dia) {
            // Verificar si ya existe
            $stmt = $conn->prepare("
                SELECT id FROM faltas_manual 
                WHERE cod_operario = ? 
                AND fecha_falta = ?
                AND tipo_falta = 'Vacaciones'
                LIMIT 1
            ");
            $stmt->execute([$solicitud['cod_operario'], $dia]);
            
            if ($stmt->fetch()) {
                continue;
            }
            
            // Insertar en faltas_manual
            $stmt = $conn->prepare("
                INSERT INTO faltas_manual (
                    cod_operario, fecha_falta, cod_sucursal, 
                    tipo_falta, porcentaje_pago, observaciones,
                    foto_path, registrado_por, cod_contrato,
                    fecha_registro
                ) VALUES (?, ?, ?, 'Vacaciones', 100, 'Vacaciones programadas.', ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $solicitud['cod_operario'],
                $dia,
                $solicitud['cod_sucursal'],
                $solicitud['foto_soporte'],
                $solicitud['solicitado_por'],
                $solicitud['cod_contrato']
            ]);
            
            $registrosCreados++;
        }
        
        $conn->commit();
        return $registrosCreados;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error aprobando solicitud RH: " . $e->getMessage());
        return false;
    }
}

/**
 * Rechaza una solicitud
 */
function rechazarSolicitud($idSolicitud, $codRechazador) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE solicitudes_vacaciones 
            SET estado = 'Rechazado',
                rechazado_por = ?,
                fecha_rechazo = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$codRechazador, $idSolicitud]);
        
    } catch (PDOException $e) {
        error_log("Error rechazando solicitud: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si un usuario puede aprobar una solicitud
 */
function puedeAprobarSolicitud($idSolicitud, $codOperario) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT estado, solicitado_por 
        FROM solicitudes_vacaciones 
        WHERE id = ?
    ");
    $stmt->execute([$idSolicitud]);
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        return false;
    }
    
    $cargosUsuario = obtenerCargosUsuario($codOperario);
    
    if ($solicitud['estado'] === 'Pendiente') {
        if (in_array(11, $cargosUsuario)) {
            $esLider = verificarAccesoCargoUsuario($solicitud['solicitado_por'], [5, 43]);
            return $esLider;
        }
    } elseif ($solicitud['estado'] === 'Aprobado_Operaciones') {
        return in_array(13, $cargosUsuario) || in_array(28, $cargosUsuario);
    }
    
    return false;
}

/**
 * Verifica si un operario tiene un cargo específico
 */
function verificarAccesoCargoUsuario($codOperario, $cargosRequeridos) {
    global $conn;
    
    $cargosRequeridos = (array)$cargosRequeridos;
    $placeholders = implode(',', array_fill(0, count($cargosRequeridos), '?'));
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as tiene_cargo 
        FROM AsignacionNivelesCargos 
        WHERE CodOperario = ? 
        AND CodNivelesCargos IN ($placeholders)
        AND (Fin IS NULL OR Fin >= CURDATE())
    ");
    
    $params = array_merge([$codOperario], $cargosRequeridos);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['tiene_cargo'] > 0;
}

/**
 * Obtiene todos los días en un rango (incluye todos los días)
 */
function obtenerTodosDiasEnRango($fechaInicio, $fechaFin) {
    try {
        $inicio = new DateTime($fechaInicio);
        $fin = new DateTime($fechaFin);
        
        if ($fin < $inicio) {
            $temp = $inicio;
            $inicio = $fin;
            $fin = $temp;
        }
        
        $todosDias = [];
        $fechaActual = clone $inicio;
        
        while ($fechaActual <= $fin) {
            $todosDias[] = $fechaActual->format('Y-m-d');
            $fechaActual->modify('+1 day');
        }
        
        return $todosDias;
        
    } catch (Exception $e) {
        error_log("Error obteniendo todos los días: " . $e->getMessage());
        return [];
    }
}