<?php
// Este archivo es incluido desde editar_colaborador.php
require_once 'compliance_logic.php';

/**
 * Calcula el porcentaje de documentos obligatorios completados para un contrato específico
 */

// Procesar el formulario cuando se envía (para todas las pestañas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pestaña'])) {
    $pestaña = $_POST['pestaña'];

    // VERIFICAR SI ES UNA SOLICITUD DE TERMINACIÓN DE CONTRATO - DEBE ESTAR AL INICIO
    if ($pestaña == 'contrato' && isset($_POST['accion_contrato']) && $_POST['accion_contrato'] == 'terminar') {
        error_log("Procesando terminación de contrato...");

        // Procesar terminación de contrato
        if (isset($_POST['id_contrato'])) {
            $resultado = terminarContrato($_POST['id_contrato'], $_POST);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }

            // Redirigir para evitar reenvío del formulario
            header("Location: editar_colaborador.php?id=$codOperario&pestaña=contrato");
            exit();
        }
    }

    // Verificar permisos según la pestaña
    if (
        ($pestaña == 'datos-personales' || $pestaña == 'datos-contacto') && !tienePermiso('editar_colaborador', 'edicion', $cargoId)
    ) {
        $_SESSION['error'] = 'No tiene permisos para editar esta información';
        header("Location: editar_colaborador.php?id=$codOperario&pestaña=$pestaña");
        exit();
    }

    // MANEJO ESPECIAL PARA LA PESTAÑA DE CONTRATO
    if ($pestaña == 'contrato' && isset($_POST['accion_contrato'])) {
        // El procesamiento del contrato se maneja en otra sección del código
// (más abajo con isset($_POST['accion_contrato']))
// Procesar acciones de contrato
//if (isset($_POST['accion_contrato'])) {
        if ($_POST['accion_contrato'] == 'guardar') {
            // Determinar si es edición o creación
            $contratoActual = obtenerContratoActual($codOperario);
            $esEdicion = ($contratoActual && empty($contratoActual['fin_contrato']));

            $resultado = guardarContrato($codOperario, $_POST, $esEdicion, $esEdicion ? $contratoActual['CodContrato'] : null);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }

        } elseif ($_POST['accion_contrato'] == 'terminar' && isset($_POST['id_contrato'])) {
            $resultado = terminarContrato($_POST['id_contrato'], $_POST);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }
        } elseif ($_POST['accion_contrato'] == 'editar_terminacion' && isset($_POST['id_contrato_editar'])) {
            $resultado = actualizarTerminacionContrato($_POST['id_contrato_editar'], $_POST);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }
        }

        // Redirigir para evitar reenvío del formulario
        header("Location: editar_colaborador.php?id=$codOperario&pestaña=contrato");
        exit();
        //}
// Solo redirigimos aquí
//header("Location: editar_colaborador.php?id=$codOperario&pestaña=$pestaña");
//exit();
    }

    // MANEJO ESPECIAL PARA LA PESTAÑA DE INSS
    if ($pestaña == 'inss' && isset($_POST['accion_inss'])) {
        // Procesar acciones de INSS
        if ($_POST['accion_inss'] == 'agregar') {
            // Verificar si hay salario INSS actual y mostrar confirmación
            $salarioActual = obtenerSalarioINSSActual($codOperario);

            if ($salarioActual && empty($_POST['confirmacion'])) {
                // Guardar datos en sesión para mostrarlos después de la confirmación
                $_SESSION['datos_inss_pendientes'] = $_POST;
                $_SESSION['confirmacion_inss'] = true;
                header("Location: editar_colaborador.php?id=$codOperario&pestaña=inss&confirmar=1");
                exit();
            }

            $resultado = agregarSalarioINSS([
                'cod_operario' => $codOperario,
                'monto_salario_inss' => $_POST['monto_salario_inss'],
                'inicio' => $_POST['inicio'],
                'observaciones_inss' => $_POST['observaciones_inss']
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
                unset($_SESSION['datos_inss_pendientes']);
                unset($_SESSION['confirmacion_inss']);
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }

        } elseif ($_POST['accion_inss'] == 'editar' && isset($_POST['id_salario_inss'])) {
            $resultado = actualizarSalarioINSS($_POST['id_salario_inss'], [
                'monto_salario_inss' => $_POST['monto_salario_inss'],
                'observaciones_inss' => $_POST['observaciones_inss']
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }
        }

        // Redirigir para evitar reenvío del formulario
        header("Location: editar_colaborador.php?id=$codOperario&pestaña=inss");
        exit();
    }

    // Procesar acciones de adendums
    if (isset($_POST['accion_adendum'])) {
        if ($_POST['accion_adendum'] == 'agregar') {
            // Ya no necesitamos id_categoria, se maneja por el cargo
            $resultado = agregarAdendum([
                'cod_operario' => $codOperario,
                'tipo_adendum' => $_POST['tipo_adendum'],
                'cod_cargo' => $_POST['cod_cargo'],
                'salario' => $_POST['salario'],
                'sucursal' => $_POST['sucursal'],
                'fecha_inicio' => $_POST['fecha_inicio'],
                'fecha_fin' => $_POST['fecha_fin'] ?? null,
                'observaciones' => $_POST['observaciones'] ?? ''
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }

        } elseif ($_POST['accion_adendum'] == 'editar' && isset($_POST['id_adendum'])) {
            $resultado = actualizarAdendum($_POST['id_adendum'], [
                'tipo_adendum' => $_POST['tipo_adendum'],
                'cod_cargo' => $_POST['cod_cargo'],
                'salario' => $_POST['salario'],
                'sucursal' => $_POST['sucursal'],
                'fecha_inicio' => $_POST['fecha_inicio'],
                'fecha_fin' => $_POST['fecha_fin'] ?? null,
                'observaciones' => $_POST['observaciones'] ?? ''
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }
        }

        header("Location: editar_colaborador.php?id=$codOperario&pestaña=adendums");
        exit();
    }

    // Procesar finalización de adenda
    if (isset($_POST['accion_finalizar_adenda']) && $_POST['accion_finalizar_adenda'] == 'finalizar') {
        $resultado = finalizarAdenda($_POST['id_adendum_finalizar'], $_POST['fecha_fin_adenda']);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

        header("Location: editar_colaborador.php?id=$codOperario&pestaña=adendums");
        exit();
    }

    // Procesar anotaciones de bitácora
    if (isset($_POST['accion_bitacora']) && $_POST['accion_bitacora'] == 'agregar') {
        $resultado = agregarAnotacionBitacora(
            $codOperario,
            $_POST['anotacion'],
            $_SESSION['usuario_id']
        );

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

        header("Location: editar_colaborador.php?id=$codOperario&pestaña=bitacora");
        exit();
    }

    // MANEJO ESPECIAL PARA LA PESTAÑA DE CATEGORÍA
    if ($pestaña == 'categoria' && isset($_POST['accion_categoria'])) {
        if ($_POST['accion_categoria'] == 'agregar') {
            $resultado = agregarCategoriaColaborador([
                'cod_operario' => $codOperario,
                'id_categoria' => $_POST['id_categoria'],
                'fecha_inicio' => $_POST['fecha_inicio']
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }

        } elseif ($_POST['accion_categoria'] == 'editar' && isset($_POST['id_categoria_edit'])) {
            $resultado = actualizarCategoriaColaborador($_POST['id_categoria_edit'], [
                'id_categoria' => $_POST['id_categoria'],
                'fecha_inicio' => $_POST['fecha_inicio'],
                'fecha_fin' => $_POST['fecha_fin'] ?? null
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }
        }

        // Redirigir para evitar reenvío del formulario
        header("Location: editar_colaborador.php?id=$codOperario&pestaña=categoria");
        exit();
    }

    // MANEJO ESPECIAL PARA LA PESTAÑA DE MOVIMIENTOS
    if ($pestaña == 'movimientos' && isset($_POST['accion_movimiento'])) {
        if ($_POST['accion_movimiento'] == 'agregar') {
            $resultado = agregarMovimientoCargo([
                'cod_operario' => $codOperario,
                'cod_cargo' => $_POST['cod_cargo'],
                'sucursal' => $_POST['sucursal'],
                'fecha_inicio' => $_POST['fecha_inicio']
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }

        } elseif ($_POST['accion_movimiento'] == 'editar' && isset($_POST['id_movimiento'])) {
            $resultado = editarMovimientoCargo($_POST['id_movimiento'], [
                'cod_cargo' => $_POST['cod_cargo'],
                'sucursal' => $_POST['sucursal'],
                'fecha_inicio' => $_POST['fecha_inicio'],
                'fecha_fin' => $_POST['fecha_fin'] ?? null
            ]);

            if ($resultado['exito']) {
                $_SESSION['exito'] = $resultado['mensaje'];
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }
        }

        // Redirigir para evitar reenvío del formulario
        header("Location: editar_colaborador.php?id=$codOperario&pestaña=movimientos");
        exit();
    }

    // Para la pestaña de datos-personales, manejar la foto de perfil por separado
    if ($pestaña == 'datos-personales' && isset($_FILES['foto_perfil']) && !empty($_FILES['foto_perfil']['name'])) {
        if (!tienePermiso('editar_colaborador', 'edicion', $cargoId)) {
            $_SESSION['error'] = 'No tiene permisos para cambiar la foto de perfil';
            header("Location: editar_colaborador.php?id=$codOperario&pestaña=datos-personales");
            exit();
        }

        $resultado = actualizarColaborador($codOperario, $_POST, 'datos-personales');

        if ($resultado['exito']) {
            $_SESSION['exito'] = 'Foto de perfil actualizada correctamente';
            // Recargar datos actualizados
            $colaborador = obtenerColaboradorPorId($codOperario);
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

        // Si es una solicitud AJAX (desde el modal), devolver JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($resultado);
            exit();
        }
    } else {
        // Para todas las demás pestañas (EXCEPTO CONTRATO)
// Solo procesar si no es la pestaña de contrato
        if ($pestaña != 'contrato') {
            $resultado = actualizarColaborador($codOperario, $_POST, $pestaña);

            if ($resultado['exito']) {
                $_SESSION['exito'] = 'Datos actualizados correctamente';
                // Recargar datos actualizados
                $colaborador = obtenerColaboradorPorId($codOperario);
            } else {
                $_SESSION['error'] = $resultado['mensaje'];
            }
        }
    }

    header("Location: editar_colaborador.php?id=$codOperario&pestaña=$pestaña");
    exit();
}

/**
 * Obtiene un colaborador por su ID
 */
function obtenerColaboradorPorId($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT
o.*,
-- Estado calculado basado en el último contrato
IF(uc.fecha_salida IS NULL OR uc.fecha_salida > CURDATE(), 1, 0) as Operativo,
-- Campos existentes...
o.usuario,
o.clave, -- Texto plano
o.clave_hash, -- Hash
o.hospital_riesgo_laboral, -- Nuevo campo
o.foto_perfil, -- Nuevo campo
-- Obtener el cargo actual (misma lógica que en colaboradores.php)
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
(SELECT nc.Nombre
FROM AsignacionNivelesCargos anc
JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
WHERE anc.CodOperario = o.CodOperario
ORDER BY anc.Fin DESC, anc.Fecha DESC
LIMIT 1),
'Sin cargo definido'
) as cargo_nombre
FROM Operarios o
LEFT JOIN Contratos uc ON uc.cod_operario = o.CodOperario 
    AND uc.CodContrato = (
        SELECT CodContrato 
        FROM Contratos 
        WHERE cod_operario = o.CodOperario
        ORDER BY inicio_contrato DESC, CodContrato DESC
        LIMIT 1
    )
WHERE o.CodOperario = ?
");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Actualiza los datos de un colaborador según la pestaña
 */
function actualizarColaborador($codOperario, $datos, $pestaña)
{
    global $conn;

    // Debug: ver qué datos llegan
    error_log("Pestaña: " . $pestaña);
    error_log("Datos recibidos: " . print_r($datos, true));
    error_log("Files: " . print_r($_FILES, true));

    try {
        // Preparar los campos a actualizar
        $campos = [];
        $valores = [];

        if ($pestaña == 'datos-personales') {
            // Campos de datos personales
            if (isset($datos['cedula'])) {
                // Limpiar guiones de la cédula antes de guardar
                $cedulaLimpia = str_replace('-', '', $datos['cedula']);
                $campos[] = 'Cedula = ?';
                $valores[] = $cedulaLimpia;
            }

            if (isset($datos['genero'])) {
                $campos[] = 'Genero = ?';
                $valores[] = $datos['genero'];
            }

            if (isset($datos['cumpleanos'])) {
                $campos[] = 'Cumpleanos = ?';
                $valores[] = $datos['cumpleanos'];
            }

            if (isset($datos['usuario'])) {
                $campos[] = 'usuario = ?';
                $valores[] = $datos['usuario'];
            }

            if (isset($datos['clave']) && !empty($datos['clave'])) {
                // Guardar en texto plano en la columna 'clave'
                $campos[] = 'clave = ?';
                $valores[] = $datos['clave'];

                // Guardar hash en la columna 'clave_hash'
                $campos[] = 'clave_hash = ?';
                $valores[] = password_hash($datos['clave'], PASSWORD_DEFAULT);
            }

            // Campos de nombres y apellidos
            if (isset($datos['nombre'])) {
                $campos[] = 'Nombre = ?';
                $valores[] = $datos['nombre'];
            }

            if (isset($datos['nombre2'])) {
                $campos[] = 'Nombre2 = ?';
                $valores[] = $datos['nombre2'];
            }

            if (isset($datos['apellido'])) {
                $campos[] = 'Apellido = ?';
                $valores[] = $datos['apellido'];
            }

            if (isset($datos['apellido2'])) {
                $campos[] = 'Apellido2 = ?';
                $valores[] = $datos['apellido2'];
            }

            // Manejar la subida de la foto de perfil (solo si se subió una nueva)
            if (isset($_FILES['foto_perfil']) && !empty($_FILES['foto_perfil']['name'])) {
                $fotoNombre = subirFotoPerfil($_FILES['foto_perfil'], $codOperario);
                if ($fotoNombre) {
                    $campos[] = 'foto_perfil = ?';
                    $valores[] = $fotoNombre;

                    // Eliminar foto anterior si existe
                    $fotoAnterior = obtenerColaboradorPorId($codOperario)['foto_perfil'];
                    if ($fotoAnterior && file_exists($fotoAnterior)) {
                        unlink($fotoAnterior);
                    }
                }
            }
        } elseif ($pestaña == 'datos-contacto') {
            // Campos de datos de contacto
            if (isset($datos['direccion'])) {
                $campos[] = 'direccion = ?';
                $valores[] = $datos['direccion'];
            }

            if (isset($datos['celular'])) {
                $campos[] = 'Celular = ?';
                $valores[] = $datos['celular'];
            }

            if (isset($datos['ciudad'])) {
                $campos[] = 'Ciudad = ?';
                $valores[] = $datos['ciudad'];
            }

            if (isset($datos['telefono_casa'])) {
                $campos[] = 'telefono_casa = ?';
                $valores[] = $datos['telefono_casa'];
            }

            if (isset($datos['telefono_corporativo'])) {
                $campos[] = 'telefono_corporativo = ?';
                $valores[] = $datos['telefono_corporativo'];
            }

            if (isset($datos['email_personal'])) {
                $campos[] = 'email_personal = ?';
                $valores[] = $datos['email_personal'];
            }

            if (isset($datos['email_trabajo'])) {
                $campos[] = 'email_trabajo = ?';
                $valores[] = $datos['email_trabajo'];
            }
        } elseif ($pestaña == 'inss') {
            // Campos específicos de INSS
            if (isset($datos['codigo_inss'])) {
                $campos[] = 'codigo_inss = ?';
                $valores[] = $datos['codigo_inss'];
            }

            if (isset($datos['hospital_riesgo_laboral'])) {
                $campos[] = 'hospital_riesgo_laboral = ?';
                $valores[] = $datos['hospital_riesgo_laboral'];
            }

            // Actualizar también campos en el contrato activo
            $contratoActual = obtenerContratoActual($codOperario);
            if ($contratoActual) {
                $camposContrato = [];
                $valoresContrato = [];

                if (isset($datos['numero_planilla'])) {
                    $camposContrato[] = 'numero_planilla = ?';
                    $valoresContrato[] = $datos['numero_planilla'];
                }

                if (isset($datos['numero_nomina'])) {
                    $camposContrato[] = 'numero_nomina = ?';
                    $valoresContrato[] = $datos['numero_nomina'];
                }

                if (isset($datos['hospital_inss'])) {
                    $camposContrato[] = 'hospital_inss = ?';
                    $valoresContrato[] = $datos['hospital_inss'];
                }

                if (!empty($camposContrato)) {
                    $valoresContrato[] = $contratoActual['CodContrato'];
                    $sqlContrato = "UPDATE Contratos SET " . implode(', ', $camposContrato) . " WHERE CodContrato = ?";
                    $stmtContrato = $conn->prepare($sqlContrato);
                    $stmtContrato->execute($valoresContrato);
                }
            }
        }

        // Si no hay campos para actualizar
        if (empty($campos)) {
            return ['exito' => false, 'mensaje' => 'No se proporcionaron datos para actualizar otros datos principales'];
        }

        // Agregar el ID al final de los valores
        $valores[] = $codOperario;

        // Construir y ejecutar la consulta
        $sql = "UPDATE Operarios SET " . implode(', ', $campos) . " WHERE CodOperario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($valores);

        return ['exito' => true, 'mensaje' => 'Datos actualizados correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar los datos: ' . $e->getMessage()];
    }
}

// Obtener adendums del colaborador
$adendumsColaborador = obtenerAdendumsColaborador($codOperario);
$adendumActual = obtenerAdendumActual($codOperario);

/**
 * Obtiene la última cuenta bancaria activa de un colaborador
 */
function obtenerCuentasBancarias($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT * FROM CuentaBancaria
WHERE cod_operario = ?
ORDER BY desde DESC, fecha_hora_reg_sys DESC
LIMIT 1 -- Solo la última cuenta
");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Obtiene una cuenta bancaria específica por ID
 */
function obtenerCuentaBancariaPorId($idCuenta)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT * FROM CuentaBancaria
WHERE id = ?
");
    $stmt->execute([$idCuenta]);
    return $stmt->fetch();
}

/**
 * Agrega una nueva cuenta bancaria
 */
function agregarCuentaBancaria($datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
INSERT INTO CuentaBancaria (cod_operario, numero_cuenta, titular, banco, moneda, desde)
VALUES (?, ?, ?, ?, ?, ?)
");

        $stmt->execute([
            $datos['cod_operario'],
            $datos['numero_cuenta'],
            $datos['titular'],
            $datos['banco'],
            $datos['moneda'],
            $datos['desde']
        ]);

        return ['exito' => true, 'mensaje' => 'Cuenta bancaria agregada correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al agregar cuenta bancaria: ' . $e->getMessage()];
    }
}

/**
 * Actualiza una cuenta bancaria existente
 */
function actualizarCuentaBancaria($idCuenta, $datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
UPDATE CuentaBancaria
SET numero_cuenta = ?, titular = ?, banco = ?, moneda = ?, desde = ?
WHERE id = ?
");

        $stmt->execute([
            $datos['numero_cuenta'],
            $datos['titular'],
            $datos['banco'],
            $datos['moneda'],
            $datos['desde'],
            $idCuenta
        ]);

        return ['exito' => true, 'mensaje' => 'Cuenta bancaria actualizada correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar cuenta bancaria: ' . $e->getMessage()];
    }
}

/**
 * Elimina una cuenta bancaria
 */
function eliminarCuentaBancaria($idCuenta)
{
    global $conn;

    try {
        $stmt = $conn->prepare("DELETE FROM CuentaBancaria WHERE id = ?");
        $stmt->execute([$idCuenta]);

        return ['exito' => true, 'mensaje' => 'Cuenta bancaria eliminada correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensage' => 'Error al eliminar cuenta bancaria: ' . $e->getMessage()];
    }
}

/**
 * Obtiene los contactos de emergencia de un colaborador
 */
function obtenerContactosEmergencia($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT * FROM ContactosEmergencia
WHERE cod_operario = ?
ORDER BY fecha_hora_reg_sys DESC
");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Obtiene un contacto de emergencia específico por ID
 */
function obtenerContactoEmergenciaPorId($idContacto)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT * FROM ContactosEmergencia
WHERE id = ?
");
    $stmt->execute([$idContacto]);
    return $stmt->fetch();
}

/**
 * Agrega un nuevo contacto de emergencia
 */
function agregarContactoEmergencia($datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
INSERT INTO ContactosEmergencia (cod_operario, nombre_contacto, parentesco, telefono_movil, telefono_casa,
telefono_trabajo)
VALUES (?, ?, ?, ?, ?, ?)
");

        $stmt->execute([
            $datos['cod_operario'],
            $datos['nombre_contacto'],
            $datos['parentesco'],
            $datos['telefono_movil'],
            $datos['telefono_casa'] ?? '',
            $datos['telefono_trabajo'] ?? ''
        ]);

        return ['exito' => true, 'mensaje' => 'Contacto de emergencia agregado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al agregar contacto de emergencia: ' . $e->getMessage()];
    }
}

/**
 * Actualiza un contacto de emergencia existente
 */
function actualizarContactoEmergencia($idContacto, $datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
UPDATE ContactosEmergencia
SET nombre_contacto = ?, parentesco = ?, telefono_movil = ?, telefono_casa = ?, telefono_trabajo = ?
WHERE id = ?
");

        $stmt->execute([
            $datos['nombre_contacto'],
            $datos['parentesco'],
            $datos['telefono_movil'],
            $datos['telefono_casa'] ?? '',
            $datos['telefono_trabajo'] ?? '',
            $idContacto
        ]);

        return ['exito' => true, 'mensaje' => 'Contacto de emergencia actualizado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar contacto de emergencia: ' . $e->getMessage()];
    }
}

/**
 * Elimina un contacto de emergencia
 */
function eliminarContactoEmergencia($idContacto)
{
    global $conn;

    try {
        $stmt = $conn->prepare("DELETE FROM ContactosEmergencia WHERE id = ?");
        $stmt->execute([$idContacto]);

        return ['exito' => true, 'mensaje' => 'Contacto de emergencia eliminado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al eliminar contacto de emergencia: ' . $e->getMessage()];
    }
}

// Procesar acciones de cuentas bancarias
if (isset($_POST['accion_cuenta'])) {
    if ($_POST['accion_cuenta'] == 'agregar') {
        $resultado = agregarCuentaBancaria([
            'cod_operario' => $codOperario,
            'numero_cuenta' => $_POST['numero_cuenta'],
            'titular' => $_POST['titular'],
            'banco' => $_POST['banco'],
            'moneda' => $_POST['moneda'],
            'desde' => $_POST['desde']
        ]);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

    } elseif ($_POST['accion_cuenta'] == 'editar' && isset($_POST['id_cuenta'])) {
        $resultado = actualizarCuentaBancaria($_POST['id_cuenta'], [
            'numero_cuenta' => $_POST['numero_cuenta'],
            'titular' => $_POST['titular'],
            'banco' => $_POST['banco'],
            'moneda' => $_POST['moneda'],
            'desde' => $_POST['desde']
        ]);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

    } elseif ($_POST['accion_cuenta'] == 'eliminar' && isset($_POST['id_cuenta'])) {
        $resultado = eliminarCuentaBancaria($_POST['id_cuenta']);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: editar_colaborador.php?id=$codOperario&pestaña=datos-personales");
    exit();
}

// Procesar acciones de contactos de emergencia
if (isset($_POST['accion_contacto'])) {
    if ($_POST['accion_contacto'] == 'agregar') {
        $resultado = agregarContactoEmergencia([
            'cod_operario' => $codOperario,
            'nombre_contacto' => $_POST['nombre_contacto'],
            'parentesco' => $_POST['parentesco'],
            'telefono_movil' => $_POST['telefono_movil'],
            'telefono_casa' => $_POST['telefono_casa'] ?? '',
            'telefono_trabajo' => $_POST['telefono_trabajo'] ?? ''
        ]);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

    } elseif ($_POST['accion_contacto'] == 'editar' && isset($_POST['id_contacto'])) {
        $resultado = actualizarContactoEmergencia($_POST['id_contacto'], [
            'nombre_contacto' => $_POST['nombre_contacto'],
            'parentesco' => $_POST['parentesco'],
            'telefono_movil' => $_POST['telefono_movil'],
            'telefono_casa' => $_POST['telefono_casa'] ?? '',
            'telefono_trabajo' => $_POST['telefono_trabajo'] ?? ''
        ]);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

    } elseif ($_POST['accion_contacto'] == 'eliminar' && isset($_POST['id_contacto'])) {
        $resultado = eliminarContactoEmergencia($_POST['id_contacto']);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: editar_colaborador.php?id=$codOperario&pestaña=contactos-emergencia");
    exit();
}

// Procesar acciones de archivos adjuntos
if (isset($_POST['accion_adjunto'])) {
    if ($_POST['accion_adjunto'] == 'agregar' && !empty($_POST['adjuntos_unificados_json']) && $_POST['adjuntos_unificados_json'] !== '[]') {

        $resultado = agregarArchivoAdjunto([
            'cod_operario' => $codOperario,
            'pestaña' => $_POST['pestaña_adjunto'],
            'tipo_documento' => $_POST['tipo_documento_adjunto_hidden'] ?? ($_POST['tipo_documento'] ?? null),
            'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?? null,
            'descripcion' => $_POST['descripcion_adjunto'] ?? '',
            'cod_usuario_subio' => $_SESSION['usuario_id'],
            'cod_adendum_asociado' => $_POST['cod_adendum_asociado'] ?? null,
            'adjuntos_unificados_json' => $_POST['adjuntos_unificados_json']
        ]);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

    } elseif ($_POST['accion_adjunto'] == 'eliminar' && isset($_POST['id_adjunto'])) {
        $resultado = eliminarArchivoAdjunto($_POST['id_adjunto']);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }
    }

    // Redirigir a la pestaña actual
    header("Location: editar_colaborador.php?id=$codOperario&pestaña=" . $_POST['pestaña_adjunto']);
    exit();
}

// Obtener cuentas bancarias del colaborador
$cuentasBancarias = obtenerCuentasBancarias($codOperario);

// Obtener contactos de emergencia del colaborador
$contactosEmergencia = obtenerContactosEmergencia($codOperario);

// Obtener salarios del colaborador
$salarios = obtenerSalarios($codOperario);

// Procesar acciones de salarios
if (isset($_POST['accion_salario'])) {
    if ($_POST['accion_salario'] == 'agregar') {
        $resultado = agregarSalario([
            'cod_operario' => $codOperario,
            'monto' => $_POST['monto'],
            'inicio' => $_POST['inicio'],
            'fin' => $_POST['fin'] ?? '',
            'frecuencia_pago' => $_POST['frecuencia_pago'],
            'observaciones' => $_POST['observaciones'] ?? ''
        ]);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

    } elseif ($_POST['accion_salario'] == 'editar' && isset($_POST['id_salario'])) {
        $resultado = actualizarSalario($_POST['id_salario'], [
            'monto' => $_POST['monto'],
            'inicio' => $_POST['inicio'],
            'fin' => $_POST['fin'] ?? '',
            'frecuencia_pago' => $_POST['frecuencia_pago'],
            'observaciones' => $_POST['observaciones'] ?? ''
        ]);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }

    } elseif ($_POST['accion_salario'] == 'eliminar' && isset($_POST['id_salario'])) {
        $resultado = eliminarSalario($_POST['id_salario']);

        if ($resultado['exito']) {
            $_SESSION['exito'] = $resultado['mensaje'];
        } else {
            $_SESSION['error'] = $resultado['mensaje'];
        }
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: editar_colaborador.php?id=$codOperario&pestaña=salario");
    exit();
}

// Obtener salarios INSS del colaborador
$salariosINSS = obtenerSalariosINSS($codOperario);
$salarioINSSActual = obtenerSalarioINSSActual($codOperario);
$planillasPatronales = obtenerPlanillasPatronales();

/**
 * Obtiene el contrato actual de un colaborador
 */
function obtenerContratoActual($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT *,
COALESCE(codigo_manual_contrato, CONCAT('CTO-', CodContrato)) as codigo_contrato_display
FROM Contratos
WHERE cod_operario = ?
AND (fin_contrato IS NULL OR fin_contrato >= CURDATE())
ORDER BY inicio_contrato DESC
LIMIT 1
");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Obtiene la asignación de cargo actual de un colaborador
 */
function obtenerAsignacionCargoActual($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT * FROM AsignacionNivelesCargos
WHERE CodOperario = ?
AND (Fin IS NULL OR Fin >= CURDATE())
ORDER BY Fecha DESC
LIMIT 1
");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Obtiene la categoría actual de un colaborador
 */
function obtenerCategoriaActual($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT * FROM OperariosCategorias
WHERE CodOperario = ?
AND (FechaFin IS NULL OR FechaFin >= CURDATE())
ORDER BY FechaInicio DESC
LIMIT 1
");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Obtiene el salario actual de un colaborador (salario inicial del contrato)
 */
function obtenerSalarioActual($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT
c.salario_inicial as monto,
c.frecuencia_pago,
c.inicio_contrato as inicio,
NULL as fin,
'salario_inicial' as tipo
FROM Contratos c
WHERE c.cod_operario = ?
AND (c.fin_contrato IS NULL OR c.fin_contrato >= CURDATE())
ORDER BY c.inicio_contrato DESC
LIMIT 1
");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Obtiene todos los cargos disponibles
 */
function obtenerTodosCargos()
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM NivelesCargos ORDER BY Nombre");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Obtiene el nombre de un cargo por su código
 */
function obtenerNombreCargo($codCargo)
{
    global $conn;

    if (!$codCargo)
        return 'No definido';

    $stmt = $conn->prepare("SELECT Nombre FROM NivelesCargos WHERE CodNivelesCargos = ?");
    $stmt->execute([$codCargo]);
    $result = $stmt->fetch();

    return $result['Nombre'] ?? 'No definido';
}

/**
 * Obtiene todas las categorías disponibles
 */
function obtenerTodasCategorias()
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM CategoriasOperarios ORDER BY idCategoria");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Obtiene los tipos de contrato disponibles
 */
function obtenerTiposContrato()
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM TipoContrato WHERE CodTipoContrato != 3 ORDER BY nombre");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Obtiene el nombre de un tipo de contrato por su código
 */
function obtenerNombreTipoContrato($codTipoContrato)
{
    global $conn;

    if (!$codTipoContrato)
        return 'No definido';

    $stmt = $conn->prepare("SELECT nombre FROM TipoContrato WHERE CodTipoContrato = ?");
    $stmt->execute([$codTipoContrato]);
    $result = $stmt->fetch();

    return $result['nombre'] ?? 'No definido';
}

$categoriasColaborador = obtenerCategoriasColaborador($codOperario);
$categoriaActual = obtenerCategoriaActual($codOperario);
$todasCategorias = obtenerTodasCategorias();

/**
 * Obtiene el historial de categorías de un colaborador
 */
function obtenerCategoriasColaborador($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT oc.*, co.NombreCategoria, co.Peso
FROM OperariosCategorias oc
JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
WHERE oc.CodOperario = ?
ORDER BY oc.FechaInicio DESC, oc.fecha_hora_regsys DESC
");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Agrega una nueva categoría a un colaborador
 */
function agregarCategoriaColaborador($datos)
{
    global $conn;

    try {
        $conn->beginTransaction();

        // 1. Finalizar la categoría actual si existe
        $stmtFinalizar = $conn->prepare("
UPDATE OperariosCategorias
SET FechaFin = ?
WHERE CodOperario = ?
AND FechaFin IS NULL
");

        // Calcular fecha fin (día anterior al nuevo inicio)
        $nuevaFecha = new DateTime($datos['fecha_inicio']);
        $nuevaFecha->modify('-1 day');
        $fechaFinAnterior = $nuevaFecha->format('Y-m-d');

        $stmtFinalizar->execute([$fechaFinAnterior, $datos['cod_operario']]);

        // 2. Obtener fecha fin de contrato para la nueva categoría
        $fechaFinContrato = null;
        $stmtContrato = $conn->prepare("
SELECT fin_contrato FROM Contratos
WHERE cod_operario = ?
AND (fin_contrato IS NULL OR fin_contrato >= CURDATE())
ORDER BY inicio_contrato DESC
LIMIT 1
");
        $stmtContrato->execute([$datos['cod_operario']]);
        $contrato = $stmtContrato->fetch();

        if ($contrato && !empty($contrato['fin_contrato'])) {
            $fechaFinContrato = $contrato['fin_contrato'];
        }

        // 3. Insertar nueva categoría
        $stmt = $conn->prepare("
INSERT INTO OperariosCategorias (CodOperario, idCategoria, FechaInicio, FechaFin)
VALUES (?, ?, ?, ?)
");

        $stmt->execute([
            $datos['cod_operario'],
            $datos['id_categoria'],
            $datos['fecha_inicio'],
            $fechaFinContrato
        ]);

        $conn->commit();
        return ['exito' => true, 'mensaje' => 'Categoría agregada correctamente'];
    } catch (Exception $e) {
        $conn->rollBack();
        return ['exito' => false, 'mensaje' => 'Error al agregar categoría: ' . $e->getMessage()];
    }
}

/**
 * Actualiza una categoría existente
 */
function actualizarCategoriaColaborador($idCategoria, $datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
UPDATE OperariosCategorias
SET idCategoria = ?, FechaInicio = ?, FechaFin = ?
WHERE id = ?
");

        $stmt->execute([
            $datos['id_categoria'],
            $datos['fecha_inicio'],
            $datos['fecha_fin'],
            $idCategoria
        ]);

        return ['exito' => true, 'mensaje' => 'Categoría actualizada correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar categoría: ' . $e->getMessage()];
    }
}

/**
 * Guarda o actualiza la información del contrato
 */
function guardarContrato($codOperario, $datos, $esEdicion = false, $idContrato = null)
{
    global $conn;

    try {
        $conn->beginTransaction();

        error_log("Datos recibidos en guardarContrato:");
        error_log("Es edición: " . ($esEdicion ? 'SÍ' : 'NO'));
        error_log("ID Contrato: " . ($idContrato ?: 'NUEVO'));
        error_log(print_r($datos, true));

        // VERIFICAR SI EL CÓDIGO MANUAL DE CONTRATO YA EXISTE (solo si se proporciona)
        if (!empty($datos['codigo_manual_contrato'])) {
            // Determinar si estamos realmente editando un contrato existente
// Buscar si hay un contrato activo para este operario
            $stmtContratoExistente = $conn->prepare("
SELECT CodContrato, codigo_manual_contrato
FROM Contratos
WHERE cod_operario = ?
AND (fecha_salida IS NULL OR fecha_salida = '0000-00-00')
AND (fin_contrato IS NULL OR fin_contrato >= CURDATE())
ORDER BY inicio_contrato DESC
LIMIT 1
");
            $stmtContratoExistente->execute([$codOperario]);
            $contratoExistente = $stmtContratoExistente->fetch();

            if ($contratoExistente) {
                // Si estamos editando un contrato existente
// Solo validar si el código manual está cambiando
                if ($datos['codigo_manual_contrato'] !== $contratoExistente['codigo_manual_contrato']) {
                    $stmtCheck = $conn->prepare("
SELECT CodContrato FROM Contratos
WHERE codigo_manual_contrato = ?
AND CodContrato != ?
");
                    $stmtCheck->execute([
                        $datos['codigo_manual_contrato'],
                        $contratoExistente['CodContrato']
                    ]);

                    if ($stmtCheck->rowCount() > 0) {
                        return ['exito' => false, 'mensaje' => 'El código de contrato ya existe. Debe ser único.'];
                    }
                }
                // Si el código no cambió, no hacer nada (es válido)
            } else {
                // Si estamos creando un NUEVO contrato (no existe uno activo)
                $stmtCheck = $conn->prepare("
SELECT CodContrato FROM Contratos
WHERE codigo_manual_contrato = ?
");
                $stmtCheck->execute([$datos['codigo_manual_contrato']]);

                if ($stmtCheck->rowCount() > 0) {
                    return ['exito' => false, 'mensaje' => 'El código de contrato ya existe. Debe ser único.'];
                }
            }
        }

        // DETERMINAR CORRECTAMENTE SI ES EDICIÓN
        $contratoActual = obtenerContratoActual($codOperario);
        $esEdicionReal = ($contratoActual && empty($contratoActual['fecha_salida']));

        if ($esEdicionReal && $contratoActual) {
            // MODO EDICIÓN - Actualizar contrato existente
            $idContrato = $contratoActual['CodContrato'];

            // 1. Obtener el CodAsignacionNivelesCargos asociado al contrato
            $stmtGetAsignacion = $conn->prepare("
SELECT CodAsignacionNivelesCargos FROM Contratos WHERE CodContrato = ?
");
            $stmtGetAsignacion->execute([$idContrato]);
            $contratoInfo = $stmtGetAsignacion->fetch();
            $codAsignacion = $contratoInfo['CodAsignacionNivelesCargos'];

            // 2. Actualizar AsignacionNivelesCargos usando el código obtenido
            if ($codAsignacion) {
                $stmtAsignacion = $conn->prepare("
UPDATE AsignacionNivelesCargos
SET CodNivelesCargos = ?, Sucursal = ?, CodTipoContrato = ?, Fecha = ?,
fecha_ultima_modificacion = NOW(), usuario_ultima_modificacion = ?
WHERE CodAsignacionNivelesCargos = ?
");
                $stmtAsignacion->execute([
                    $datos['cod_cargo'],
                    $datos['sucursal'],
                    $datos['cod_tipo_contrato'],
                    $datos['inicio_contrato'],
                    $_SESSION['usuario_id'],
                    $codAsignacion
                ]);
            }

            // 3. Actualizar Contratos - INCLUYENDO AUDITORÍA
            $sqlContrato = "
UPDATE Contratos
SET cod_tipo_contrato = ?, codigo_manual_contrato = ?,
inicio_contrato = ?, ciudad = ?,
observaciones = ?, cod_sucursal_contrato = ?,
fecha_ultima_modificacion = NOW(), usuario_ultima_modificacion = ?
";

            $params = [
                $datos['cod_tipo_contrato'],
                $datos['codigo_manual_contrato'] ?? null,
                $datos['inicio_contrato'],
                $datos['ciudad'],
                $datos['observaciones'],
                $datos['sucursal'],
                $_SESSION['usuario_id'] // Usuario que modifica
            ];

            // Solo actualizar fin_contrato si se proporciona explícitamente un nuevo valor
// y el tipo de contrato es temporal (1)
//if (isset($datos['fin_contrato']) && !empty($datos['fin_contrato']) && $datos['cod_tipo_contrato'] == 1) {
// $sqlContrato .= ", fin_contrato = ?";
// $params[] = $datos['fin_contrato'];
//}
// Si no se proporciona y es contrato temporal, mantener el valor existente

            // MODIFICACIÓN AQUÍ: Manejar fin_contrato según tipo de contrato
            if ($datos['cod_tipo_contrato'] == 2) {
                // Si es contrato indefinido (tipo 2), establecer fin_contrato como NULL
                $sqlContrato .= ", fin_contrato = NULL";
            } elseif ($datos['cod_tipo_contrato'] == 1 && !empty($datos['fin_contrato'])) {
                // Si es temporal (tipo 1) y hay fecha fin, actualizarla
                $sqlContrato .= ", fin_contrato = ?";
                $params[] = $datos['fin_contrato'];
            }
            // Si no cumple ninguna condición, no modificar fin_contrato

            // Eliminada esta sección del código ya que ahora es por archivo adjunto por pestaña, no una foto:
/*
// Manejar la subida de archivos si se proporciona uno nuevo
if (!empty($_FILES['foto_contrato']['name'])) {
$fotoNombre = subirArchivo($_FILES['foto_contrato'], 'contratos');
if ($fotoNombre) {
$sqlContrato .= ", foto = ?";
$params[] = $fotoNombre;
}
}
*/

            $sqlContrato .= " WHERE CodContrato = ?";
            $params[] = $idContrato;

            $stmtContrato = $conn->prepare($sqlContrato);
            $stmtContrato->execute($params);

            // 4. Actualizar el salario inicial y frecuencia en la tabla Contratos
            if (isset($datos['monto_salario'])) {
                $stmtContratoSalario = $conn->prepare("
UPDATE Contratos
SET salario_inicial = ?, frecuencia_pago = ?,
fecha_ultima_modificacion = NOW(), usuario_ultima_modificacion = ?
WHERE CodContrato = ?
");
                $stmtContratoSalario->execute([
                    $datos['monto_salario'],
                    $datos['frecuencia_pago'] ?? 'quincenal',
                    $_SESSION['usuario_id'], // Usuario que modifica
                    $idContrato
                ]);
            }

        } else {
            // MODO CREACIÓN - Nuevo contrato (mantener código original)
// 1. Guardar en AsignacionNivelesCargos - USAR LA FECHA DE INICIO DEL CONTRATO
            $stmtAsignacion = $conn->prepare("
INSERT INTO AsignacionNivelesCargos (CodOperario, CodNivelesCargos, Fecha, Sucursal, CodTipoContrato,
cod_usuario_creador)
VALUES (?, ?, ?, ?, ?, ?)
");
            $stmtAsignacion->execute([
                $codOperario,
                $datos['cod_cargo'],
                $datos['inicio_contrato'],
                $datos['sucursal'],
                $datos['cod_tipo_contrato'],
                $_SESSION['usuario_id']
            ]);

            $codAsignacion = $conn->lastInsertId();

            // 2. Guardar en Contratos - INCLUYENDO CAMPOS DE AUDITORÍA
            $stmtContrato = $conn->prepare("
INSERT INTO Contratos (cod_tipo_contrato, codigo_manual_contrato, cod_operario,
inicio_contrato, fin_contrato, ciudad, observaciones, foto,
cod_sucursal_contrato, CodAsignacionNivelesCargos, cod_usuario_creador)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

            // Manejar la subida de archivos
            $fotoNombre = null;
            if (!empty($_FILES['foto_contrato']['name'])) {
                $fotoNombre = subirArchivo($_FILES['foto_contrato'], 'contratos');
            }

            $stmtContrato->execute([
                $datos['cod_tipo_contrato'],
                $datos['codigo_manual_contrato'] ?? null,
                $codOperario, // Este es el colaborador que se está editando
                $datos['inicio_contrato'],
                ($datos['cod_tipo_contrato'] == 1) ? $datos['fin_contrato'] : null,
                $datos['ciudad'],
                $datos['observaciones'],
                $fotoNombre,
                $datos['sucursal'],
                $codAsignacion,
                $_SESSION['usuario_id'] // Guardar el usuario que creó el contrato
            ]);

            $codContrato = $conn->lastInsertId();

            // 3. ACTUALIZAR EL CAMPO OPERATIVO A 1 EN LA TABLA OPERARIOS
            $stmtOperativo = $conn->prepare("
UPDATE Operarios
SET Operativo = 1
WHERE CodOperario = ?
");
            $stmtOperativo->execute([$codOperario]);

            // 4. Guardar salario inicial y frecuencia directamente en Contratos
            $stmtUpdateContrato = $conn->prepare("
UPDATE Contratos
SET salario_inicial = ?, frecuencia_pago = ?
WHERE CodContrato = ?
");
            $stmtUpdateContrato->execute([
                $datos['monto_salario'],
                $datos['frecuencia_pago'] ?? 'quincenal',
                $codContrato
            ]);

            // 5. Guardar categoría si aplica (solo para cargos 2 y 5)
            if (in_array($datos['cod_cargo'], [2, 5]) && !empty($datos['id_categoria'])) {
                $idCategoria = ($datos['cod_cargo'] == 2) ? 5 : 1;

                $stmtCategoria = $conn->prepare("
INSERT INTO OperariosCategorias (CodOperario, idCategoria, FechaInicio)
VALUES (?, ?, ?)
");
                $stmtCategoria->execute([
                    $codOperario,
                    $idCategoria,
                    $datos['inicio_contrato'],
                    //$_SESSION['usuario_id'] cod_usuario_creador en el OperariosCategorias si se quiere agregar también ese dato a dicha tabla
                ]);
            }
        }

        $conn->commit();
        return ['exito' => true, 'mensaje' => 'Contrato ' . ($esEdicionReal ? 'actualizado' : 'guardado') . ' correctamente'];

    } catch (Exception $e) {
        $conn->rollBack();
        return [
            'exito' => false,
            'mensaje' => 'Error al ' . ($esEdicionReal ? 'actualizar' : 'guardar') . ' el contrato: ' .
                $e->getMessage()
        ];
    }
}

/**
 * Función para subir archivos
 */
function subirArchivo($archivo, $directorio)
{
    $directorioDestino = "uploads/$directorio/";

    // Crear directorio si no existe
    if (!file_exists($directorioDestino)) {
        mkdir($directorioDestino, 0777, true);
    }

    $nombreArchivo = uniqid() . '_' . basename($archivo['name']);
    $rutaCompleta = $directorioDestino . $nombreArchivo;

    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return $rutaCompleta;
    }

    return null;
}

/**
 * Termina un contrato y da de baja completa al colaborador - CORREGIDA
 */
function terminarContrato($codContrato, $datos)
{
    global $conn;

    try {
        // Verificar si ya hay una transacción activa
        $transaccionActiva = $conn->inTransaction();

        if (!$transaccionActiva) {
            $conn->beginTransaction();
        }

        // Obtener información del contrato y operario
        $stmtInfo = $conn->prepare("
SELECT cod_operario, inicio_contrato, cod_tipo_contrato, fin_contrato
FROM Contratos WHERE CodContrato = ?
");
        $stmtInfo->execute([$codContrato]);
        $infoContrato = $stmtInfo->fetch();

        if (!$infoContrato) {
            throw new Exception("No se encontró el contrato especificado");
        }

        $codOperario = $infoContrato['cod_operario'];

        // Usar fecha de salida como fecha de terminación
        $fechaSalida = $datos['fecha_terminacion'];

        // DEBUG: Log para ver qué datos llegan
        error_log("Terminando contrato $codContrato para operario $codOperario con fecha $fechaSalida");

        // CORRECCIÓN: No actualizar fin_contrato, solo fecha_salida y fecha_liquidacion (si se proporciona)
        $sql = "
UPDATE Contratos
SET fecha_salida = ?,
fecha_liquidacion = ?,
motivo = ?,
cod_tipo_salida = ?,
dias_trabajados = ?,
monto_indemnizacion = ?,
devolucion_herramientas_trabajo = ?,
persona_recibe_herramientas_trabajo = ?,
Finalizado = 1,
fecha_ultima_modificacion = NOW(),
usuario_ultima_modificacion = ?
WHERE CodContrato = ?
";

        $params = [
            $fechaSalida,
            !empty($datos['fecha_liquidacion']) ? $datos['fecha_liquidacion'] : null, // NULL en lugar de "0000-00-00"
            $datos['motivo_salida'],
            $datos['tipo_salida'],
            $datos['dias_trabajados'] ?? 0,
            $datos['monto_indemnizacion'] ?? 0,
            $datos['devolucion_herramientas'] ? 1 : 0,
            $datos['persona_recibe_herramientas'] ?? '',
            $_SESSION['usuario_id'], // Usuario que modifica
            $codContrato
        ];

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Verificar si se actualizó correctamente
        if ($stmt->rowCount() === 0) {
            throw new Exception("No se pudo actualizar el contrato");
        }

        error_log("Contrato actualizado correctamente");

        // Manejar foto de renuncia si se subió
        if (!empty($_FILES['foto_renuncia']['name'])) {
            $fotoRenuncia = subirArchivo($_FILES['foto_renuncia'], 'renuncias');
            if ($fotoRenuncia) {
                $stmtFoto = $conn->prepare("UPDATE Contratos SET foto_solicitud_renuncia = ? WHERE CodContrato = ?");
                $stmtFoto->execute([$fotoRenuncia, $codContrato]);
            }
        }

        // Dar de baja completa al colaborador
        error_log("Ejecutando baja completa para operario $codOperario");
        $resultadoBaja = darDeBajaCompleta($codOperario, $fechaSalida, $datos['motivo_salida']);

        if (!$resultadoBaja['exito']) {
            throw new Exception($resultadoBaja['mensaje']);
        }

        // Solo hacer commit si iniciamos la transacción
        if (!$transaccionActiva) {
            $conn->commit();
        }

        error_log("Terminación de contrato completada exitosamente");
        return ['exito' => true, 'mensaje' => 'Contrato terminado y baja completa realizada correctamente'];

    } catch (Exception $e) {
        error_log("Error en terminarContrato: " . $e->getMessage());

        // Solo hacer rollback si iniciamos la transacción
        if (!$transaccionActiva && $conn->inTransaction()) {
            $conn->rollBack();
        }

        return ['exito' => false, 'mensaje' => 'Error al terminar el contrato: ' . $e->getMessage()];
    }
}

/**
 * Obtiene los tipos de salida disponibles
 */
function obtenerTiposSalida()
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM TipoSalida ORDER BY nombre");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Obtiene los salarios de un colaborador (todos, incluyendo el inicial y los adicionales)
 */
function obtenerSalarios($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT so.*, c.inicio_contrato, c.fin_contrato,
CASE WHEN so.CodSalarioOperario = c.CodSalario THEN 1 ELSE 0 END as es_salario_inicial
FROM SalarioOperario so
JOIN Contratos c ON so.cod_contrato = c.CodContrato
WHERE c.cod_operario = ?
ORDER BY so.inicio DESC, so.fecha_hora_reg_sys DESC
");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Obtiene un salario específico por ID
 */
function obtenerSalarioPorId($idSalario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT so.*, c.cod_operario
FROM SalarioOperario so
JOIN Contratos c ON so.cod_contrato = c.CodContrato
WHERE so.CodSalarioOperario = ?
");
    $stmt->execute([$idSalario]);
    return $stmt->fetch();
}

/**
 * Agrega un nuevo salario (independiente del salario inicial del contrato)
 */
function agregarSalario($datos)
{
    global $conn;

    try {
        // Obtener el contrato activo del colaborador
        $stmtContrato = $conn->prepare("
SELECT CodContrato FROM Contratos
WHERE cod_operario = ?
AND (fin_contrato IS NULL OR fin_contrato >= CURDATE())
ORDER BY inicio_contrato DESC
LIMIT 1
");
        $stmtContrato->execute([$datos['cod_operario']]);
        $contrato = $stmtContrato->fetch();

        if (!$contrato) {
            return ['exito' => false, 'mensaje' => 'No se encontró un contrato activo para este colaborador'];
        }

        $stmt = $conn->prepare("
INSERT INTO SalarioOperario (cod_contrato, monto, inicio, fin, frecuencia_pago, observaciones)
VALUES (?, ?, ?, ?, ?, ?)
");

        $stmt->execute([
            $contrato['CodContrato'],
            $datos['monto'],
            $datos['inicio'],
            $datos['fin'] ?: null,
            $datos['frecuencia_pago'],
            $datos['observaciones'] ?: null
        ]);

        return ['exito' => true, 'mensaje' => 'Salario adicional agregado correctamente'];

    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al agregar salario adicional: ' . $e->getMessage()];
    }
}

/**
 * Actualiza un salario existente
 */
function actualizarSalario($idSalario, $datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
UPDATE SalarioOperario
SET monto = ?, inicio = ?, fin = ?, frecuencia_pago = ?, observaciones = ?
WHERE CodSalarioOperario = ?
");

        $stmt->execute([
            $datos['monto'],
            $datos['inicio'],
            $datos['fin'] ?: null,
            $datos['frecuencia_pago'],
            $datos['observaciones'] ?: null,
            $idSalario
        ]);

        return ['exito' => true, 'mensaje' => 'Salario actualizado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar salario: ' . $e->getMessage()];
    }
}

/**
 * Elimina un salario
 */
function eliminarSalario($idSalario)
{
    global $conn;

    try {
        $stmt = $conn->prepare("DELETE FROM SalarioOperario WHERE CodSalarioOperario = ?");
        $stmt->execute([$idSalario]);

        return ['exito' => true, 'mensaje' => 'Salario eliminado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al eliminar salario: ' . $e->getMessage()];
    }
}

/**
 * Obtiene los salarios INSS de un colaborador
 */
function obtenerSalariosINSS($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT si.*, p.nombre_planilla
FROM SalarioINSS si
LEFT JOIN Contratos c ON si.cod_contrato = c.CodContrato
LEFT JOIN PatronalesINSS p ON c.numero_planilla = p.CodPlanilla
WHERE c.cod_operario = ?
ORDER BY si.inicio DESC, si.fecha_hora_reg_sys DESC
");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Obtiene un salario INSS específico por ID
 */
function obtenerSalarioINSSPorId($idSalarioINSS)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT si.*, p.nombre_planilla
FROM SalarioINSS si
LEFT JOIN Contratos c ON si.cod_contrato = c.CodContrato
LEFT JOIN PatronalesINSS p ON c.numero_planilla = p.CodPlanilla
WHERE si.id = ?
");
    $stmt->execute([$idSalarioINSS]);
    return $stmt->fetch();
}

/**
 * Actualiza la información de terminación de un contrato existente
 */
function actualizarTerminacionContrato($codContrato, $datos)
{
    global $conn;

    try {
        $conn->beginTransaction();

        $sql = "
UPDATE Contratos
SET fin_contrato = ?,
fecha_salida = ?,
fecha_liquidacion = ?,
motivo = ?,
cod_tipo_salida = ?,
dias_trabajados = ?,
monto_indemnizacion = ?,
devolucion_herramientas_trabajo = ?,
persona_recibe_herramientas_trabajo = ?,
fecha_ultima_modificacion = NOW(),
usuario_ultima_modificacion = ?
WHERE CodContrato = ?
";

        $params = [
            !empty($datos['fecha_fin_contrato']) ? $datos['fecha_fin_contrato'] : null,
            !empty($datos['fecha_terminacion']) ? $datos['fecha_terminacion'] : null,
            !empty($datos['fecha_liquidacion']) ? $datos['fecha_liquidacion'] : null,
            $datos['motivo_salida'] ?? null,
            $datos['tipo_salida'] ?? null,
            $datos['dias_trabajados'] ?? 0,
            $datos['monto_indemnizacion'] ?? 0,
            isset($datos['devolucion_herramientas']) ? 1 : 0,
            $datos['persona_recibe_herramientas'] ?? '',
            $_SESSION['usuario_id'],
            $codContrato
        ];

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Manejar foto de renuncia si se subió
        if (!empty($_FILES['foto_renuncia']['name'])) {
            $fotoRenuncia = subirArchivo($_FILES['foto_renuncia'], 'renuncias');
            if ($fotoRenuncia) {
                $stmtFoto = $conn->prepare("UPDATE Contratos SET foto_solicitud_renuncia = ? WHERE CodContrato = ?");
                $stmtFoto->execute([$fotoRenuncia, $codContrato]);
            }
        }

        $conn->commit();
        return ['exito' => true, 'mensaje' => 'Información de terminación actualizada correctamente'];

    } catch (Exception $e) {
        $conn->rollBack();
        return ['exito' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()];
    }
}

/**
 * Obtiene el salario INSS actual de un colaborador
 */
function obtenerSalarioINSSActual($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT si.*, p.nombre_planilla
FROM SalarioINSS si
LEFT JOIN Contratos c ON si.cod_contrato = c.CodContrato
LEFT JOIN PatronalesINSS p ON c.numero_planilla = p.CodPlanilla
WHERE c.cod_operario = ?
AND (si.final IS NULL OR si.final >= CURDATE())
ORDER BY si.inicio DESC
LIMIT 1
");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Obtiene todas las planillas patronales disponibles
 */
function obtenerPlanillasPatronales()
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM PatronalesINSS ORDER BY nombre_planilla");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Agrega un nuevo salario INSS
 */
function agregarSalarioINSS($datos)
{
    global $conn;

    try {
        $conn->beginTransaction();

        // Obtener el contrato actual del operario
        $stmtContrato = $conn->prepare("
SELECT CodContrato FROM Contratos
WHERE cod_operario = ?
AND (fin_contrato IS NULL OR fin_contrato >= CURDATE())
ORDER BY inicio_contrato DESC
LIMIT 1
");
        $stmtContrato->execute([$datos['cod_operario']]);
        $contrato = $stmtContrato->fetch();

        if (!$contrato) {
            throw new Exception("No se encontró un contrato activo para este colaborador");
        }

        // Insertar nuevo salario INSS
        $stmt = $conn->prepare("
INSERT INTO SalarioINSS
(cod_contrato, monto_salario_inss, inicio, observaciones_inss)
VALUES (?, ?, ?, ?)
");

        $stmt->execute([
            $contrato['CodContrato'],
            $datos['monto_salario_inss'],
            $datos['inicio'],
            $datos['observaciones_inss']
        ]);

        $idSalarioINSS = $conn->lastInsertId();

        // Actualizar fechas fin automáticamente
        actualizarFechasFinAutomaticamente($datos['cod_operario'], $datos['inicio'], 'salario_inss', $idSalarioINSS);

        $conn->commit();
        return ['exito' => true, 'mensaje' => 'Salario INSS agregado correctamente'];
    } catch (Exception $e) {
        $conn->rollBack();
        return ['exito' => false, 'mensaje' => 'Error al agregar salario INSS: ' . $e->getMessage()];
    }
}

/**
 * Actualiza un salario INSS existente
 */
function actualizarSalarioINSS($idSalarioINSS, $datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
UPDATE SalarioINSS
SET monto_salario_inss = ?, hospital_inss = ?, hospital_riesgo_laboral = ?, observaciones_inss = ?
WHERE id = ?
");

        $stmt->execute([
            $datos['monto_salario_inss'],
            $datos['hospital_inss'],
            $datos['hospital_riesgo_laboral'],
            $datos['observaciones_inss'],
            $idSalarioINSS
        ]);

        return ['exito' => true, 'mensaje' => 'Salario INSS actualizado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar salario INSS: ' . $e->getMessage()];
    }
}

/**
 * Obtiene una categoría específica por ID
 */
function obtenerCategoriaPorId($idCategoria)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT oc.*, co.NombreCategoria, co.Peso
FROM OperariosCategorias oc
JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
WHERE oc.id = ?
");
    $stmt->execute([$idCategoria]);
    return $stmt->fetch();
}

/**
 * Obtiene el historial de contratos de un colaborador
 */
function obtenerHistorialContratos($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT
c.*,
tc.nombre as tipo_contrato,
uc.Nombre as nombre_creador, uc.Apellido as apellido_creador,
um.Nombre as nombre_modificador, um.Apellido as apellido_modificador,
COALESCE(
(SELECT nc.Nombre
FROM AsignacionNivelesCargos anc
JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
WHERE anc.CodOperario = c.cod_operario
AND DATE(anc.Fecha) <= c.inicio_contrato AND (anc.Fin IS NULL OR DATE(anc.Fin)>= c.inicio_contrato)
    ORDER BY anc.Fecha DESC
    LIMIT 1),
    'Sin cargo definido'
    ) as cargo,
    (SELECT COUNT(*) FROM Contratos c2 WHERE c2.cod_operario = ?) as total_contratos
    FROM Contratos c
    LEFT JOIN TipoContrato tc ON c.cod_tipo_contrato = tc.CodTipoContrato
    LEFT JOIN Operarios uc ON c.cod_usuario_creador = uc.CodOperario
    LEFT JOIN Operarios um ON c.usuario_ultima_modificacion = um.CodOperario
    WHERE c.cod_operario = ?
    ORDER BY c.inicio_contrato DESC, c.fin_contrato DESC
    ");
    $stmt->execute([$codOperario, $codOperario]);
    return $stmt->fetchAll();
}

// Obtener historial de contratos
$historialContratos = obtenerHistorialContratos($codOperario);

/**
 * Obtiene los archivos adjuntos de un colaborador por pestaña ordenados por adendum y fecha
 */
function obtenerArchivosAdjuntos($codOperario, $pestaña)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT
    a.*,
    COALESCE(t.pestaña, a.pestaña) as pestaña,
    COALESCE(t.nombre_clave, a.tipo_documento) as tipo_documento,
    o.Nombre as nombre_usuario,
    o.Apellido as apellido_usuario,
    c.codigo_manual_contrato,
    c.inicio_contrato,
    c.fin_contrato,
    tc.nombre as tipo_contrato,
    anc.TipoAdendum,
    anc.Salario as salario_adendum,
    nc.Nombre as nombre_cargo_adendum,
    co.NombreCategoria as nombre_categoria_adendum
    FROM ArchivosAdjuntos a
    LEFT JOIN contratos_tiposDocumentos t ON a.id_tipo_documento = t.id
    JOIN Operarios o ON a.cod_usuario_subio = o.CodOperario
    LEFT JOIN Contratos c ON a.cod_contrato_asociado = c.CodContrato
    LEFT JOIN TipoContrato tc ON c.cod_tipo_contrato = tc.CodTipoContrato
    LEFT JOIN AsignacionNivelesCargos anc ON a.cod_adendum_asociado = anc.CodAsignacionNivelesCargos
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN CategoriasOperarios co ON anc.CodNivelesCargos = co.idCategoria
    WHERE a.cod_operario = ? AND COALESCE(t.pestaña, a.pestaña) = ?
    ORDER BY
    a.cod_adendum_asociado DESC,
    a.obligatorio DESC,
    a.fecha_subida DESC
    ");
    $stmt->execute([$codOperario, $pestaña]);
    return $stmt->fetchAll();
}

/**
 * Agrega un nuevo archivo adjunto con lógica de contrato asociado - CORREGIDA
 */
function agregarArchivoAdjunto($datos)
{
    global $conn;

    try {
        $resultadosExito = [];
        $resultadosError = [];
        $fechaVencimiento = !empty($datos['fecha_vencimiento']) ? $datos['fecha_vencimiento'] : null;

        // Determinar si se debe asociar a un contrato o adendum
        $codContratoAsociado = null;
        $codAdendumAsociado = null;
        $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria', 'expediente-digital'];

        if (in_array($datos['pestaña'], $pestañasConContrato)) {
            $contratoActual = obtenerContratoActual($datos['cod_operario']);
            if ($contratoActual) {
                $codContratoAsociado = $contratoActual['CodContrato'];

                if ($datos['pestaña'] == 'adendums' && !empty($datos['cod_adendum_asociado'])) {
                    $codAdendumAsociado = $datos['cod_adendum_asociado'];
                } elseif ($datos['pestaña'] == 'adendums') {
                    $ultimoAdendum = obtenerUltimoAdendumActivo($datos['cod_operario']);
                    if ($ultimoAdendum) {
                        $codAdendumAsociado = $ultimoAdendum['CodAsignacionNivelesCargos'];
                    } else {
                        return ['exito' => false, 'mensaje' => 'No se puede subir archivo porque no hay adendums activos'];
                    }
                }
            } else {
                return ['exito' => false, 'mensaje' => 'No se puede subir el archivo porque no hay un contrato activo'];
            }
        }

        // Resolver tipo de documento: puede venir como ID numérico o como clave de texto
        $id_tipo_documento_raw = !empty($datos['tipo_documento']) ? $datos['tipo_documento'] : null;
        $id_tipo_documento = null;
        $obligatorio = 0;
        $categoria = 'opcional';
        $clave_tipo_documento = null; // la clave de texto guardada en tipo_documento de la tabla

        if (!empty($id_tipo_documento_raw)) {
            // Intentar obtener info del tipo de documento por ID numérico
            if (is_numeric($id_tipo_documento_raw)) {
                $stmtTipo = $conn->prepare("SELECT id, nombre_clave, es_obligatorio FROM contratos_tiposDocumentos WHERE id = ? AND activo = 1");
                $stmtTipo->execute([$id_tipo_documento_raw]);
                $tipoInfo = $stmtTipo->fetch();
                if ($tipoInfo) {
                    $id_tipo_documento = $tipoInfo['id'];
                    $clave_tipo_documento = $tipoInfo['nombre_clave'];
                    if ($tipoInfo['es_obligatorio']) {
                        $obligatorio = 1;
                        $categoria = 'obligatorio';
                    }
                }
            } elseif ($id_tipo_documento_raw !== 'otro') {
                // es una clave textual (compatibilidad con código viejo si existiera)
                $tiposPermitidos = obtenerTiposDocumentosPorPestaña($datos['pestaña']);
                if (isset($tiposPermitidos['ids'][$id_tipo_documento_raw])) {
                    $id_tipo_documento = $tiposPermitidos['ids'][$id_tipo_documento_raw];
                    $clave_tipo_documento = $id_tipo_documento_raw;
                    if (in_array($id_tipo_documento_raw, array_keys($tiposPermitidos['obligatorios']))) {
                        $obligatorio = 1;
                        $categoria = 'obligatorio';
                    }
                }
            }

            // Verificar si ya existe un archivo del mismo tipo (solo si es obligatorio)
            if ($id_tipo_documento && $obligatorio) {
                $stmtCheck = $conn->prepare("
                    SELECT COUNT(*) FROM ArchivosAdjuntos
                    WHERE cod_operario = ? AND pestaña = ? AND id_tipo_documento = ?
                    AND (cod_contrato_asociado = ? OR ? IS NULL)
                ");
                $stmtCheck->execute([
                    $datos['cod_operario'],
                    $datos['pestaña'],
                    $id_tipo_documento,
                    $codContratoAsociado,
                    $codContratoAsociado
                ]);

                if ($stmtCheck->fetchColumn() > 0) {
                    return ['exito' => false, 'mensaje' => 'Ya existe un archivo de este tipo. Elimine el existente antes de subir uno nuevo.'];
                }
            }
        }

        // Crear directorio si no existe
        $directorio = "../../uploads/adjuntos/" . $datos['cod_operario'] . "/";
        if (!file_exists($directorio)) {
            mkdir($directorio, 0777, true);
        }

        // PROCESAR COLA UNIFICADA (JSON de Base64)
        if (!empty($datos['adjuntos_unificados_json'])) {
            $adjuntos = json_decode($datos['adjuntos_unificados_json'], true);
            if (is_array($adjuntos) && count($adjuntos) > 0) {
                foreach ($adjuntos as $index => $item) {
                    $base64 = $item['data'];
                    if (preg_match('/^data:(image\/(\w+)|application\/pdf);base64,/', $base64, $match)) {
                        $fullType = $match[1];
                        $extension = ($fullType === 'application/pdf') ? 'pdf' : strtolower($match[3]);

                        $imgData = substr($base64, strpos($base64, ',') + 1);
                        $imgData = base64_decode($imgData);

                        $nombreOriginal = $item['nombre'];
                        $nombreArchivoLocal = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreOriginal);
                        $rutaCompleta = $directorio . $nombreArchivoLocal;

                        if (file_put_contents($rutaCompleta, $imgData)) {
                            $stmt = $conn->prepare("
                                INSERT INTO ArchivosAdjuntos
                                (cod_operario, cod_contrato_asociado, cod_adendum_asociado, pestaña, tipo_documento, id_tipo_documento, obligatorio, categoria,
                                nombre_archivo, descripcion, tamaño, ruta_archivo, cod_usuario_subio, fecha_vencimiento)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $datos['cod_operario'],
                                $codContratoAsociado,
                                $codAdendumAsociado,
                                $datos['pestaña'],
                                $clave_tipo_documento, // nombre_clave resuelto desde el ID o clave de texto
                                $id_tipo_documento,
                                $obligatorio,
                                $categoria,
                                $nombreOriginal,
                                $datos['descripcion'] ?? '',
                                strlen($imgData),
                                $rutaCompleta,
                                $datos['cod_usuario_subio'],
                                $fechaVencimiento
                            ]);
                            $resultadosExito[] = "Archivo '" . $nombreOriginal . "' guardado";
                        } else {
                            $resultadosError[] = "Error al guardar '" . $nombreOriginal . "'";
                        }
                    }
                }
            }
        }

        if (count($resultadosExito) > 0) {
            return ['exito' => true, 'mensaje' => "Se subieron " . count($resultadosExito) . " archivo(s) correctamente."];
        } elseif (count($resultadosError) > 0) {
            return ['exito' => false, 'mensaje' => implode(", ", $resultadosError)];
        } else {
            return ['exito' => false, 'mensaje' => 'No se seleccionaron archivos para subir'];
        }

    } catch (Exception $e) {
        error_log("Error en agregarArchivoAdjunto: " . $e->getMessage());
        return ['exito' => false, 'mensaje' => 'Error al procesar: ' . $e->getMessage()];
    }
}

/**
 * Elimina un archivo adjunto
 */
function eliminarArchivoAdjunto($idArchivo)
{
    global $conn;

    try {
        // Primero obtener la ruta del archivo
        $stmt = $conn->prepare("SELECT ruta_archivo FROM ArchivosAdjuntos WHERE id = ?");
        $stmt->execute([$idArchivo]);
        $archivo = $stmt->fetch();

        if ($archivo && file_exists($archivo['ruta_archivo'])) {
            unlink($archivo['ruta_archivo']);
        }

        // Eliminar de la base de datos
        $stmt = $conn->prepare("DELETE FROM ArchivosAdjuntos WHERE id = ?");
        $stmt->execute([$idArchivo]);

        return ['exito' => true, 'mensaje' => 'Archivo eliminado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al eliminar el archivo: ' . $e->getMessage()];
    }
}

// Obtener archivos adjuntos de la pestaña actual
$archivosAdjuntos = obtenerArchivosAdjuntos($codOperario, $pestaña_activa);

// Obtener bitácora del colaborador
$bitacoraColaborador = obtenerBitacoraColaborador($codOperario);

function obtenerContratoConINSS($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT c.*, p.nombre_planilla
    FROM Contratos c
    LEFT JOIN PatronalesINSS p ON c.numero_planilla = p.CodPlanilla
    WHERE c.cod_operario = ?
    AND (c.fin_contrato IS NULL OR c.fin_contrato >= CURDATE())
    ORDER BY c.inicio_contrato DESC
    LIMIT 1
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

function subirFotoPerfil($archivo, $codOperario)
{
    // Crear directorio específico para el colaborador
    $directorioDestino = "../../uploads/fotos_perfil/" . $codOperario . "/";

    // Crear directorio si no existe
    if (!file_exists($directorioDestino)) {
        mkdir($directorioDestino, 0777, true);
    }

    // Validar que sea una imagen
    $tipoArchivo = $archivo['type'];
    if (!in_array($tipoArchivo, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        return null;
    }

    // Validar tamaño (máximo 2MB)
    $tamañoMaximo = 2 * 1024 * 1024;
    if ($archivo['size'] > $tamañoMaximo) {
        return null;
    }

    // Generar nombre único
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = 'perfil_' . uniqid() . '.' . $extension;
    $rutaCompleta = $directorioDestino . $nombreArchivo;

    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        // Redimensionar imagen para optimización
        redimensionarImagen($rutaCompleta, 200, 200);

        // Guardar en BD solo la ruta relativa (sin los ../)
        return 'uploads/fotos_perfil/' . $codOperario . '/' . $nombreArchivo;
    }

    return null;
}

function redimensionarImagen($ruta, $ancho, $alto)
{
    $info = getimagesize($ruta);
    $tipo = $info[2];

    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $imagen = imagecreatefromjpeg($ruta);
            break;
        case IMAGETYPE_PNG:
            $imagen = imagecreatefrompng($ruta);
            break;
        case IMAGETYPE_GIF:
            $imagen = imagecreatefromgif($ruta);
            break;
        default:
            return false;
    }

    $nuevaImagen = imagecreatetruecolor($ancho, $alto);

    // Mantener transparencia para PNG y GIF
    if ($tipo == IMAGETYPE_PNG || $tipo == IMAGETYPE_GIF) {
        imagecolortransparent($nuevaImagen, imagecolorallocatealpha($nuevaImagen, 0, 0, 0, 127));
        imagealphablending($nuevaImagen, false);
        imagesavealpha($nuevaImagen, true);
    }

    imagecopyresampled($nuevaImagen, $imagen, 0, 0, 0, 0, $ancho, $alto, $info[0], $info[1]);

    switch ($tipo) {
        case IMAGETYPE_JPEG:
            imagejpeg($nuevaImagen, $ruta, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($nuevaImagen, $ruta, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($nuevaImagen, $ruta);
            break;
    }

    imagedestroy($imagen);
    imagedestroy($nuevaImagen);

    return true;
}

/**
 * Función para dar de baja completa a un colaborador - MEJORADA
 * Cierra todas las entradas activas en las diferentes tablas
 */
function darDeBajaCompleta($codOperario, $fechaSalida, $motivo = '')
{
    global $conn;

    try {
        // NO iniciar transacción aquí - ya se maneja en terminarContrato
        error_log("Ejecutando darDeBajaCompleta para operario: $codOperario, fecha: $fechaSalida");

        // 1. Cerrar asignaciones de cargo activas
        $stmtAsignaciones = $conn->prepare("
    UPDATE AsignacionNivelesCargos
    SET Fin = ?
    WHERE CodOperario = ?
    AND (Fin IS NULL OR Fin > ?)
    ");
        $stmtAsignaciones->execute([$fechaSalida, $codOperario, $fechaSalida]);
        error_log("Asignaciones de cargo actualizadas: " . $stmtAsignaciones->rowCount());

        // 2. Cerrar categorías activas
        $stmtCategorias = $conn->prepare("
    UPDATE OperariosCategorias
    SET FechaFin = ?
    WHERE CodOperario = ?
    AND (FechaFin IS NULL OR FechaFin > ?)
    ");
        $stmtCategorias->execute([$fechaSalida, $codOperario, $fechaSalida]);
        error_log("Categorías actualizadas: " . $stmtCategorias->rowCount());

        // 3. Cerrar salarios activos
        $stmtSalarios = $conn->prepare("
    UPDATE SalarioOperario so
    JOIN Contratos c ON so.cod_contrato = c.CodContrato
    SET so.fin = ?
    WHERE c.cod_operario = ?
    AND (so.fin IS NULL OR so.fin > ?)
    ");
        $stmtSalarios->execute([$fechaSalida, $codOperario, $fechaSalida]);
        error_log("Salarios actualizados: " . $stmtSalarios->rowCount());

        // 4. Cerrar salarios INSS activos
        $stmtSalariosINSS = $conn->prepare("
    UPDATE SalarioINSS si
    JOIN Contratos c ON si.cod_contrato = c.CodContrato
    SET si.final = ?
    WHERE c.cod_operario = ?
    AND (si.final IS NULL OR si.final > ?)
    ");
        $stmtSalariosINSS->execute([$fechaSalida, $codOperario, $fechaSalida]);
        error_log("Salarios INSS actualizados: " . $stmtSalariosINSS->rowCount());

        // 5. Cerrar adendums activos
        $stmtAdendums = $conn->prepare("
    UPDATE OperariosCategorias
    SET FechaFin = ?
    WHERE CodOperario = ?
    AND TipoAdendum IS NOT NULL
    AND (FechaFin IS NULL OR FechaFin > ?)
    ");
        $stmtAdendums->execute([$fechaSalida, $codOperario, $fechaSalida]);
        error_log("Adendums actualizados: " . $stmtAdendums->rowCount());

        // 6. Desactivar al operario (Operativo = 0)
        $stmtOperario = $conn->prepare("
    UPDATE Operarios
    SET Operativo = 0, Fin = ?
    WHERE CodOperario = ?
    ");
        $stmtOperario->execute([$fechaSalida, $codOperario]);
        error_log("Operario desactivado: " . $stmtOperario->rowCount());

        // NO hacer commit aquí - se maneja en la función principal
        error_log("Baja completa ejecutada exitosamente");
        return ['exito' => true, 'mensaje' => 'Baja completa realizada correctamente'];

    } catch (Exception $e) {
        error_log("Error en darDeBajaCompleta: " . $e->getMessage());
        return ['exito' => false, 'mensaje' => 'Error al realizar la baja completa: ' . $e->getMessage()];
    }
}

/**
 * Actualiza automáticamente las fechas fin cuando se agregan nuevos registros - CORREGIDA
 */
function actualizarFechasFinAutomaticamente($codOperario, $nuevaFechaInicio, $tipo, $idRegistroNuevo = null)
{
    global $conn;

    // Verificar si ya hay una transacción activa
    $transaccionActiva = $conn->inTransaction();

    try {
        if (!$transaccionActiva) {
            $conn->beginTransaction();
        }

        // Obtener fecha fin de contrato (si existe)
        $fechaFinContrato = null;
        $stmtContrato = $conn->prepare("
    SELECT fin_contrato FROM Contratos
    WHERE cod_operario = ?
    AND (fin_contrato IS NULL OR fin_contrato >= CURDATE())
    ORDER BY inicio_contrato DESC
    LIMIT 1
    ");
        $stmtContrato->execute([$codOperario]);
        $contrato = $stmtContrato->fetch();

        if ($contrato && !empty($contrato['fin_contrato'])) {
            $fechaFinContrato = $contrato['fin_contrato'];
        }

        // Calcular fecha fin anterior (día anterior al nuevo inicio)
        $nuevaFecha = new DateTime($nuevaFechaInicio);
        $nuevaFecha->modify('-1 day');
        $fechaFinAnterior = $nuevaFecha->format('Y-m-d');

        if ($tipo == 'salario') {
            // Finalizar salario anterior
            $stmt = $conn->prepare("
    UPDATE SalarioOperario so
    JOIN Contratos c ON so.cod_contrato = c.CodContrato
    SET so.fin = ?
    WHERE c.cod_operario = ?
    AND so.fin IS NULL
    AND so.CodSalarioOperario != ?
    ");
            $stmt->execute([$fechaFinAnterior, $codOperario, $idRegistroNuevo]);

        } elseif ($tipo == 'salario_inss') {
            // Finalizar salario INSS anterior
            $stmt = $conn->prepare("
    UPDATE SalarioINSS si
    JOIN Contratos c ON si.cod_contrato = c.CodContrato
    SET si.final = ?
    WHERE c.cod_operario = ?
    AND si.final IS NULL
    AND si.id != ?
    ");
            $stmt->execute([$fechaFinAnterior, $codOperario, $idRegistroNuevo]);

        } elseif ($tipo == 'cargo') {
            // Finalizar cargo anterior
            $stmt = $conn->prepare("
    UPDATE AsignacionNivelesCargos
    SET Fin = ?
    WHERE CodOperario = ?
    AND Fin IS NULL
    AND CodAsignacionNivelesCargos != ?
    ");
            $stmt->execute([$fechaFinAnterior, $codOperario, $idRegistroNuevo]);

        } elseif ($tipo == 'categoria') {
            // Finalizar categoría anterior
            $stmt = $conn->prepare("
    UPDATE OperariosCategorias
    SET FechaFin = ?
    WHERE CodOperario = ?
    AND FechaFin IS NULL
    AND id != ?
    ");
            $stmt->execute([$fechaFinAnterior, $codOperario, $idRegistroNuevo]);
        }

        // Si hay fecha fin de contrato, establecerla en el nuevo registro
        if ($fechaFinContrato) {
            if ($tipo == 'salario') {
                $stmt = $conn->prepare("
    UPDATE SalarioOperario
    SET fin = ?
    WHERE CodSalarioOperario = ?
    ");
                $stmt->execute([$fechaFinContrato, $idRegistroNuevo]);

            } elseif ($tipo == 'salario_inss') {
                $stmt = $conn->prepare("
    UPDATE SalarioINSS
    SET final = ?
    WHERE id = ?
    ");
                $stmt->execute([$fechaFinContrato, $idRegistroNuevo]);
            }
        }

        // Solo hacer commit si iniciamos la transacción
        if (!$transaccionActiva) {
            $conn->commit();
        }

        return true;

    } catch (Exception $e) {
        // Solo hacer rollback si iniciamos la transacción
        if (!$transaccionActiva && $conn->inTransaction()) {
            $conn->rollBack();
        }

        return false;
    }
}

/**
 * Obtiene el historial de cargos de un colaborador - MEJORADA
 */
function obtenerHistorialCargos($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT
    anc.*,
    nc.Nombre as nombre_cargo,
    s.nombre as nombre_sucursal,
    tc.nombre as nombre_tipo_contrato,
    c.codigo_manual_contrato
    FROM AsignacionNivelesCargos anc
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
    LEFT JOIN TipoContrato tc ON anc.CodTipoContrato = tc.CodTipoContrato
    LEFT JOIN Contratos c ON anc.codigo_contrato_asociado = c.codigo_manual_contrato
    WHERE anc.CodOperario = ?
    ORDER BY anc.Fecha DESC, anc.Fin DESC
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Agrega un nuevo movimiento de cargo con tipo de contrato 3 por defecto - CORREGIDA
 */
function agregarMovimientoCargo($datos)
{
    global $conn;

    // Verificar si ya hay una transacción activa
    $transaccionActiva = $conn->inTransaction();

    try {
        if (!$transaccionActiva) {
            $conn->beginTransaction();
        }

        // Obtener el contrato activo para asociar el código manual
        $contratoActual = obtenerContratoActual($datos['cod_operario']);
        $codigoContratoAsociado = $contratoActual ? $contratoActual['codigo_manual_contrato'] : null;

        $stmt = $conn->prepare("
    INSERT INTO AsignacionNivelesCargos
    (CodOperario, CodNivelesCargos, Fecha, Sucursal, CodTipoContrato, codigo_contrato_asociado)
    VALUES (?, ?, ?, ?, 3, ?) -- CodTipoContrato 3 por defecto para movimientos
    ");

        $stmt->execute([
            $datos['cod_operario'],
            $datos['cod_cargo'],
            $datos['fecha_inicio'],
            $datos['sucursal'],
            $codigoContratoAsociado // Asociar al código del contrato activo
            // CodTipoContrato 3 se establece directamente en la consulta
        ]);

        $idMovimiento = $conn->lastInsertId();

        // Actualizar fechas fin automáticamente
        //actualizarFechasFinAutomaticamente($datos['cod_operario'], $datos['fecha_inicio'], 'cargo', $idMovimiento);

        // Solo hacer commit si iniciamos la transacción
        if (!$transaccionActiva) {
            $conn->commit();
        }

        return ['exito' => true, 'mensaje' => 'Movimiento de cargo agregado correctamente'];
    } catch (Exception $e) {
        // Solo hacer rollback si iniciamos la transacción
        if (!$transaccionActiva && $conn->inTransaction()) {
            $conn->rollBack();
        }

        return ['exito' => false, 'mensaje' => 'Error al agregar movimiento: ' . $e->getMessage()];
    }
}

/**
 * Edita un movimiento de cargo existente (mantiene el tipo de contrato existente)
 */
function editarMovimientoCargo($idMovimiento, $datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
    UPDATE AsignacionNivelesCargos
    SET CodNivelesCargos = ?, Sucursal = ?, Fecha = ?, Fin = ?
    WHERE CodAsignacionNivelesCargos = ?
    ");

        $stmt->execute([
            $datos['cod_cargo'],
            $datos['sucursal'],
            $datos['fecha_inicio'],
            $datos['fecha_fin'],
            $idMovimiento
        ]);

        return ['exito' => true, 'mensaje' => 'Movimiento de cargo actualizado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar movimiento: ' . $e->getMessage()];
    }
}

/**
 * Obtiene el historial de adendums de un colaborador
 */
function obtenerAdendumsColaborador($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT
    anc.*,
    co.NombreCategoria,
    co.Peso,
    nc.Nombre as nombre_cargo,
    s.nombre as nombre_sucursal,
    c.codigo_manual_contrato,
    c.inicio_contrato,
    tc.nombre as tipo_contrato
    FROM AsignacionNivelesCargos anc
    LEFT JOIN CategoriasOperarios co ON anc.CodNivelesCargos = co.idCategoria
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
    LEFT JOIN Contratos c ON anc.CodContrato = c.CodContrato
    LEFT JOIN TipoContrato tc ON c.cod_tipo_contrato = tc.CodTipoContrato
    WHERE anc.CodOperario = ?
    AND anc.TipoAdendum IS NOT NULL
    ORDER BY anc.Fecha DESC, anc.Fin DESC
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Obtiene el adendum activo actual de un colaborador
 */
function obtenerAdendumActual($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT anc.*
    FROM AsignacionNivelesCargos anc
    WHERE anc.CodOperario = ?
    AND anc.TipoAdendum IS NOT NULL
    AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
    ORDER BY anc.Fecha DESC
    LIMIT 1
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Agrega un nuevo adendum (solo inserta en AsignacionNivelesCargos)
 */
function agregarAdendum($datos)
{
    global $conn;

    try {
        $conn->beginTransaction();

        // 1. Obtener el contrato activo del colaborador
        $contratoActual = obtenerContratoActual($datos['cod_operario']);
        if (!$contratoActual) {
            throw new Exception("No se encontró un contrato activo para este colaborador");
        }

        // 2. Obtener el último adendum activo para cerrarlo automáticamente
        $ultimoAdendum = obtenerUltimoAdendumActivo($datos['cod_operario']);

        // 3. CERRAR AUTOMÁTICAMENTE EL ADENDA ANTERIOR con fecha un día antes del nuevo
        if ($ultimoAdendum && empty($ultimoAdendum['Fin'])) {
            // Calcular fecha de cierre: un día antes de la fecha de inicio del nuevo adendum
            $fechaCierreAnterior = date('Y-m-d', strtotime($datos['fecha_inicio'] . ' -1 day'));

            $stmtCerrar = $conn->prepare("
                UPDATE AsignacionNivelesCargos
                SET Fin = ?, fecha_ultima_modificacion = NOW(), usuario_ultima_modificacion = ?
                WHERE CodAsignacionNivelesCargos = ?
            ");
            $stmtCerrar->execute([$fechaCierreAnterior, $_SESSION['usuario_id'], $ultimoAdendum['CodAsignacionNivelesCargos']]);

            error_log("Adenda anterior (ID: {$ultimoAdendum['CodAsignacionNivelesCargos']}) cerrada automáticamente con fecha: {$fechaCierreAnterior}");
        }

        // 4. Determinar fecha fin para el nuevo adendum
        $fechaFinNuevo = null;
        if (!empty($datos['fecha_fin'])) {
            $fechaFinNuevo = $datos['fecha_fin'];
        } else {
            // Si no se proporciona fecha fin, usar la fecha fin del contrato (si existe)
            if (!empty($contratoActual['fin_contrato'])) {
                $fechaFinNuevo = $contratoActual['fin_contrato'];
            }
            // Si no hay fecha fin de contrato, se queda como NULL (adendum indefinido)
        }

        // 5. Insertar el nuevo adendum en AsignacionNivelesCargos
        $stmt = $conn->prepare("
    INSERT INTO AsignacionNivelesCargos (
    CodOperario, CodContrato, codigo_contrato_asociado,
    TipoAdendum, CodNivelesCargos, Sucursal,
    Fecha, Fin, Observaciones, Salario, es_activo,
    cod_usuario_creador
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");

        $stmt->execute([
            $datos['cod_operario'],
            $contratoActual['CodContrato'],
            $contratoActual['codigo_manual_contrato'],
            $datos['tipo_adendum'],
            $datos['cod_cargo'],
            $datos['sucursal'],
            $datos['fecha_inicio'],
            $fechaFinNuevo, // Puede ser NULL
            $datos['observaciones'],
            $datos['salario'] ?? 0.00,
            $_SESSION['usuario_id']
        ]);

        $idAdendum = $conn->lastInsertId();

        $conn->commit();
        return ['exito' => true, 'mensaje' => 'Adendum agregado correctamente', 'id' => $idAdendum];

    } catch (Exception $e) {
        $conn->rollBack();
        return ['exito' => false, 'mensaje' => 'Error al agregar adendum: ' . $e->getMessage()];
    }
}

/**
 * Finaliza una adenda asignando fecha de fin
 */
function finalizarAdenda($idAdendum, $fechaFin)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
    UPDATE AsignacionNivelesCargos
    SET Fin = ?, fecha_ultima_modificacion = NOW(), usuario_ultima_modificacion = ?
    WHERE CodAsignacionNivelesCargos = ?
    AND TipoAdendum IS NOT NULL
    ");

        $stmt->execute([$fechaFin, $_SESSION['usuario_id'], $idAdendum]);

        return ['exito' => true, 'mensaje' => 'Adenda finalizada correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al finalizar adenda: ' . $e->getMessage()];
    }
}

/**
 * Obtiene el último adendum (activo o inactivo) de un colaborador
 */
function obtenerUltimoAdendum($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT * FROM AsignacionNivelesCargos
    WHERE CodOperario = ?
    AND TipoAdendum IS NOT NULL
    ORDER BY Fecha DESC
    LIMIT 1
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

/**
 * Obtiene un adendum específico por ID
 */
function obtenerAdendumPorId($idAdendum)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT
    anc.*,
    nc.Nombre as nombre_cargo,
    s.nombre as nombre_sucursal
    FROM AsignacionNivelesCargos anc
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
    WHERE anc.CodAsignacionNivelesCargos = ?
    ");
    $stmt->execute([$idAdendum]);
    return $stmt->fetch();
}

/**
 * Actualiza un adendum existente
 */
function actualizarAdendum($idAdendum, $datos)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
    UPDATE AsignacionNivelesCargos
    SET TipoAdendum = ?, CodNivelesCargos = ?, Salario = ?,
    Sucursal = ?, Fecha = ?, Fin = ?, Observaciones = ?,
    fecha_ultima_modificacion = NOW(), usuario_ultima_modificacion = ?
    WHERE CodAsignacionNivelesCargos = ?
    ");

        // Manejar fecha fin: si está vacía, establecer como NULL
        $fechaFin = !empty($datos['fecha_fin']) ? $datos['fecha_fin'] : null;

        $stmt->execute([
            $datos['tipo_adendum'],
            $datos['cod_cargo'],
            $datos['salario'],
            $datos['sucursal'],
            $datos['fecha_inicio'],
            $fechaFin, // Puede ser NULL
            $datos['observaciones'],
            $_SESSION['usuario_id'],
            $idAdendum
        ]);

        return ['exito' => true, 'mensaje' => 'Adendum actualizado correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al actualizar adendum: ' . $e->getMessage()];
    }
}

/**
 * Obtiene el historial de bitácora de un colaborador
 */
function obtenerBitacoraColaborador($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT
    b.*,
    CONCAT(o.Nombre, ' ', o.Apellido) as nombre_usuario
    FROM BitacoraColaborador b
    JOIN Operarios o ON b.cod_usuario_registro = o.CodOperario
    WHERE b.cod_operario = ?
    ORDER BY b.fecha_registro DESC
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetchAll();
}

/**
 * Agrega una anotación a la bitácora
 */
function agregarAnotacionBitacora($codOperario, $anotacion, $codUsuario)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
    INSERT INTO BitacoraColaborador (cod_operario, anotacion, cod_usuario_registro)
    VALUES (?, ?, ?)
    ");

        $stmt->execute([$codOperario, $anotacion, $codUsuario]);

        return ['exito' => true, 'mensaje' => 'Anotación agregada correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al agregar anotación: ' . $e->getMessage()];
    }
}

/**
 * Obtiene todos los archivos adjuntos de un colaborador agrupados por categoría mejorada
 */
function obtenerExpedienteDigitalCompleto($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT
    a.*,
    COALESCE(t.pestaña, a.pestaña) as pestaña,
    COALESCE(t.nombre_clave, a.tipo_documento) as tipo_documento,
    o.Nombre as nombre_usuario,
    o.Apellido as apellido_usuario,
    c.codigo_manual_contrato,
    c.inicio_contrato,
    c.fin_contrato,
    tc.nombre as tipo_contrato,
    oc.TipoAdendum,
    oc.Salario as salario_adendum,
    nc.Nombre as nombre_cargo_adendum,
    co.NombreCategoria as nombre_categoria_adendum,
    oc.FechaInicio as fecha_adendum,
    -- Determinar categoría principal mejorada
    CASE
    WHEN a.cod_adendum_asociado IS NOT NULL THEN CONCAT('Adendum - ', oc.TipoAdendum, ' (', DATE_FORMAT(oc.FechaInicio,
    '%d/%m/%Y'), ')')
    WHEN a.cod_contrato_asociado IS NULL THEN 'Archivos de Colaborador'
    ELSE CONCAT('Contrato ', COALESCE(c.codigo_manual_contrato, 'Sin código'))
    END as categoria_principal,
    -- Subcategoría (tipo de documento)
    CASE
    WHEN a.obligatorio = 1 THEN 'Documentos Obligatorios'
    ELSE 'Otros Documentos'
    END as subcategoria
    FROM ArchivosAdjuntos a
    LEFT JOIN contratos_tiposDocumentos t ON a.id_tipo_documento = t.id
    JOIN Operarios o ON a.cod_usuario_subio = o.CodOperario
    LEFT JOIN Contratos c ON a.cod_contrato_asociado = c.CodContrato
    LEFT JOIN TipoContrato tc ON c.cod_tipo_contrato = tc.CodTipoContrato
    LEFT JOIN OperariosCategorias oc ON a.cod_adendum_asociado = oc.id
    LEFT JOIN NivelesCargos nc ON oc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
    WHERE a.cod_operario = ?
    ORDER BY
    -- Primero: Archivos de Colaborador, luego Contratos, luego Adendums
    CASE
    WHEN a.cod_adendum_asociado IS NOT NULL THEN 3
    WHEN a.cod_contrato_asociado IS NULL THEN 1
    ELSE 2
    END,
    -- Segundo: Por fecha de adendum (más reciente primero)
    oc.FechaInicio DESC,
    -- Tercero: Por código manual de contrato
    COALESCE(c.codigo_manual_contrato, ''),
    -- Cuarto: Obligatorios primero
    a.obligatorio DESC,
    -- Quinto: Por fecha de subida
    a.fecha_subida DESC
    ");
    $stmt->execute([$codOperario]);
    $archivos = $stmt->fetchAll();

    return agruparArchivosPorCategoriaMejorada($archivos);
}

/**
 * Agrupa los archivos por categoría principal mejorada
 */
function agruparArchivosPorCategoriaMejorada($archivos)
{
    $agrupados = [];

    foreach ($archivos as $archivo) {
        // Asegurar que el tipo esté definido para evitar errores en la vista
        if (!isset($archivo['tipo'])) {
            $archivo['tipo'] = 'archivo';
        }

        $categoriaPrincipal = $archivo['categoria_principal'];
        $subcategoria = $archivo['subcategoria'];

        if (!isset($agrupados[$categoriaPrincipal])) {
            $agrupados[$categoriaPrincipal] = [];
        }

        if (!isset($agrupados[$categoriaPrincipal][$subcategoria])) {
            $agrupados[$categoriaPrincipal][$subcategoria] = [];
        }

        $agrupados[$categoriaPrincipal][$subcategoria][] = $archivo;
    }

    return $agrupados;
}

/**
 * Agrupa los archivos por categoría principal
 */
function agruparArchivosPorCategoria($archivos)
{
    $agrupados = [
        'Contrato' => [],
        'Adendum' => [],
        'INSS' => [],
        'Documentos Obligatorios' => [],
        'Documentos Informativos' => []
    ];

    foreach ($archivos as $archivo) {
        $categoria = $archivo['categoria_principal'];
        $agrupados[$categoria][] = $archivo;
    }

    return $agrupados;
}

/**
 * Obtiene los documentos obligatorios faltantes por pestaña
 */
function obtenerDocumentosFaltantes($codOperario)
{
    $documentosFaltantes = [];
    $pestañas = ['datos-personales', 'inss', 'contrato'];

    foreach ($pestañas as $pestaña) {
        $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña);
        $obligatorios = $tiposDocumentos['obligatorios'];

        if (empty($obligatorios))
            continue;

        global $conn;

        // Obtener documentos obligatorios ya subidos para esta pestaña
        $placeholders = str_repeat('?,', count($obligatorios) - 1) . '?';
        $tipos = array_keys($obligatorios);

        $stmt = $conn->prepare("
    SELECT tipo_documento
    FROM ArchivosAdjuntos
    WHERE cod_operario = ?
    AND pestaña = ?
    AND tipo_documento IN ($placeholders)
    AND obligatorio = 1
    ");

        $params = array_merge([$codOperario, $pestaña], $tipos);
        $stmt->execute($params);
        $subidos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Encontrar los faltantes
        $faltantes = array_diff(array_keys($obligatorios), $subidos);

        if (!empty($faltantes)) {
            $documentosFaltantes[$pestaña] = [
                'pestaña_nombre' => obtenerNombrePestaña($pestaña),
                'faltantes' => []
            ];

            foreach ($faltantes as $tipo) {
                $documentosFaltantes[$pestaña]['faltantes'][] = [
                    'tipo' => $tipo,
                    'nombre' => $obligatorios[$tipo]
                ];
            }
        }
    }

    return $documentosFaltantes;
}

/**
 * Obtiene el expediente completo agrupado por pestaña, incluyendo documentos faltantes
 */
function obtenerExpedienteCompletoConFaltantes($codOperario)
{
    global $conn;

    // 1. Obtener todos los tipos de documentos activos (configuración)
    $stmt = $conn->prepare("
        SELECT id, pestaña, nombre_clave, nombre_descriptivo, es_obligatorio, tiene_vencimiento
        FROM contratos_tiposDocumentos
        WHERE activo = 1
        ORDER BY pestaña ASC, es_obligatorio DESC, nombre_descriptivo ASC
    ");
    $stmt->execute();
    $tiposConfig = $stmt->fetchAll();

    // 2. Obtener todos los archivos subidos del colaborador con su configuración actual
    // Usamos el join para obtener los datos reales de la configuración
    $stmt = $conn->prepare("
        SELECT a.*, 
               td.pestaña as cfg_pestana, 
               td.nombre_clave as cfg_clave, 
               td.nombre_descriptivo as cfg_nombre, 
               td.es_obligatorio as cfg_obligatorio,
               o.Nombre as nombre_usuario, 
               o.Apellido as apellido_usuario
        FROM ArchivosAdjuntos a
        LEFT JOIN contratos_tiposDocumentos td ON a.id_tipo_documento = td.id
        JOIN Operarios o ON a.cod_usuario_subio = o.CodOperario
        WHERE a.cod_operario = ?
        ORDER BY a.fecha_subida DESC
    ");
    $stmt->execute([$codOperario]);
    $archivosSubidos = $stmt->fetchAll();

    // 3. Organizar archivos subidos en un mapa
    $subidosMap = [];
    foreach ($archivosSubidos as $archivo) {
        $idTipo = $archivo['id_tipo_documento'];
        $pestana = !empty($archivo['cfg_pestana']) ? $archivo['cfg_pestana'] : (!empty($archivo['pestaña']) ? $archivo['pestaña'] : 'sin_pestana');
        $clave = !empty($idTipo) ? $idTipo : (!empty($archivo['tipo_documento']) ? $archivo['tipo_documento'] : 'sin_tipo');

        if (!isset($subidosMap[$pestana]))
            $subidosMap[$pestana] = [];
        if (!isset($subidosMap[$pestana][$clave]))
            $subidosMap[$pestana][$clave] = [];

        $subidosMap[$pestana][$clave][] = $archivo;
    }

    // 4. Construir la estructura final recorriendo la configuración
    $expediente = [];

    foreach ($tiposConfig as $tipo) {
        $p = $tipo['pestaña'];
        $id = $tipo['id'];

        // Buscar archivos subidos para este tipo
        $subidos = isset($subidosMap[$p][$id]) ? $subidosMap[$p][$id] : [];
        if (isset($subidosMap[$p][$id]))
            unset($subidosMap[$p][$id]);

        // FILTRO: si no es obligatorio y no tiene archivos, NO LO ENLISTAMOS
        if (!$tipo['es_obligatorio'] && empty($subidos)) {
            continue;
        }


        if (!isset($expediente[$p])) {
            $expediente[$p] = [
                'nombre' => obtenerNombrePestaña($p),
                'documentos' => [],
                'stats' => ['total_obligatorios' => 0, 'subidos_obligatorios' => 0, 'porcentaje' => 100]
            ];
        }

        if ($tipo['es_obligatorio']) {
            $expediente[$p]['stats']['total_obligatorios']++;
            if (!empty($subidos)) {
                $expediente[$p]['stats']['subidos_obligatorios']++;
            }
        }

        $expediente[$p]['documentos'][] = [
            'tipo' => 'configurado',
            'id_tipo' => $id,
            'nombre' => $tipo['nombre_descriptivo'],
            'obligatorio' => $tipo['es_obligatorio'],
            'tiene_vencimiento' => $tipo['tiene_vencimiento'],
            'archivos' => $subidos
        ];
    }

    // 5. Agregar archivos que quedaron en el mapa (legacy o sin ID de tipo configurado)
    foreach ($subidosMap as $p => $tiposHuerfanos) {
        if (!isset($expediente[$p])) {
            $expediente[$p] = [
                'nombre' => obtenerNombrePestaña($p),
                'documentos' => [],
                'stats' => ['total_obligatorios' => 0, 'subidos_obligatorios' => 0, 'porcentaje' => 100]
            ];
        }

        foreach ($tiposHuerfanos as $clave => $archivos) {
            $expediente[$p]['documentos'][] = [
                'tipo' => 'otro',
                'id_tipo' => null,
                'nombre' => $clave === 'sin_tipo' ? 'Archivo sin tipo clasificado' : $clave,
                'obligatorio' => 0,
                'tiene_vencimiento' => 0,
                'archivos' => $archivos
            ];
        }
    }

    // Recalcular porcentajes finales por pestaña
    foreach ($expediente as &$pestana) {
        $total = $pestana['stats']['total_obligatorios'];
        $subidos = $pestana['stats']['subidos_obligatorios'];
        $pestana['stats']['porcentaje'] = $total > 0 ? round(($subidos / $total) * 100) : 100;
    }

    return $expediente;
}



/**
 * Verifica si el colaborador tiene contrato activo
 */
function tieneContratoActivo($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM Contratos
    WHERE cod_operario = ?
    AND (fin_contrato IS NULL OR fin_contrato >= CURDATE())
    ");
    $stmt->execute([$codOperario]);
    $result = $stmt->fetch();

    return $result['total'] > 0;
}

/**
 * Obtiene el último adendum activo de un colaborador
 */
function obtenerUltimoAdendumActivo($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT anc.*
    FROM AsignacionNivelesCargos anc
    WHERE anc.CodOperario = ?
    AND anc.TipoAdendum IS NOT NULL
    AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
    ORDER BY anc.Fecha DESC
    LIMIT 1
    ");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}


/**
 * Obtiene la categoría asociada a un contrato desde CategoriasOperarios
 */
function obtenerCategoriaPorContrato($codContrato)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT co.NombreCategoria
    FROM OperariosCategorias oc
    JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
    WHERE oc.CodContrato = ?
    ORDER BY oc.FechaInicio DESC
    LIMIT 1
    ");
    $stmt->execute([$codContrato]);
    $result = $stmt->fetch();

    return $result['NombreCategoria'] ?? null;
}

/**
 * Obtiene el nombre de una categoría por su ID
 */
function obtenerNombreCategoriaPorId($idCategoria)
{
    global $conn;

    if (!$idCategoria)
        return null;

    $stmt = $conn->prepare("SELECT NombreCategoria FROM CategoriasOperarios WHERE idCategoria = ?");
    $stmt->execute([$idCategoria]);
    $result = $stmt->fetch();

    return $result['NombreCategoria'] ?? null;
}

/**
 * Obtiene todas las categorías disponibles con información completa
 */
function obtenerTodasCategoriasCompletas()
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM CategoriasOperarios ORDER BY idCategoria");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Asigna o actualiza la fecha de liquidación de un contrato
 */
function asignarFechaLiquidacion($codContrato, $fechaLiquidacion)
{
    global $conn;

    try {
        $stmt = $conn->prepare("
    UPDATE Contratos
    SET fecha_liquidacion = ?
    WHERE CodContrato = ?
    ");

        $stmt->execute([$fechaLiquidacion, $codContrato]);

        return ['exito' => true, 'mensaje' => 'Fecha de liquidación asignada correctamente'];
    } catch (Exception $e) {
        return ['exito' => false, 'mensaje' => 'Error al asignar fecha de liquidación: ' . $e->getMessage()];
    }
}

// Procesar asignación de fecha de liquidación
if (isset($_POST['accion_liquidacion']) && $_POST['accion_liquidacion'] == 'asignar') {
    $resultado = asignarFechaLiquidacion($_POST['id_contrato_liquidacion'], $_POST['fecha_liquidacion']);

    if ($resultado['exito']) {
        $_SESSION['exito'] = $resultado['mensaje'];
    } else {
        $_SESSION['error'] = $resultado['mensaje'];
    }

    // Redirigir para evitar reenvío del formulario
    header("Location: editar_colaborador.php?id=$codOperario&pestaña=contrato");
    exit();
}

?>