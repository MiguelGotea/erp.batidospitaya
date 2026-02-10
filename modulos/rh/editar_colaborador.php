<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoId = $usuario['CodNivelesCargos'] ?? 0;

// Verificar acceso al módulo
if (!tienePermiso('editar_colaborador', 'vista', $cargoId)) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = $usuario['cargo_nombre'] ?? 'No definido';

// Verificar si se recibió un ID de colaborador
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'No se ha especificado un colaborador para editar';
    header('Location: colaboradores.php');
    exit();
}

$codOperario = intval($_GET['id']);

// Obtener datos del colaborador
$colaborador = obtenerColaboradorPorId($codOperario);

if (!$colaborador) {
    $_SESSION['error'] = 'Colaborador no encontrado';
    header('Location: colaboradores.php');
    exit();
}

// Determinar qué pestaña está activa (por defecto datos-personales)
$pestaña_activa = isset($_GET['pestaña']) ? $_GET['pestaña'] : 'datos-personales';

/**
 * Calcula el porcentaje de documentos obligatorios completados para un contrato específico
 */
function calcularPorcentajeDocumentosObligatoriosContrato($codOperario, $codContrato)
{
    global $conn;

    // Pestañas que tienen documentos obligatorios
    $pestañasConObligatorios = ['datos-personales', 'inss', 'contrato'];

    $totalObligatorios = 0;
    $completados = 0;

    foreach ($pestañasConObligatorios as $pestaña) {
        $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña);
        $obligatorios = $tiposDocumentos['obligatorios'];

        if (empty($obligatorios))
            continue;

        $totalObligatorios += count($obligatorios);

        // Obtener documentos obligatorios subidos para este contrato específico
        $placeholders = str_repeat('?,', count($obligatorios) - 1) . '?';
        $tipos = array_keys($obligatorios);

        $stmt = $conn->prepare("
SELECT COUNT(DISTINCT tipo_documento) as completados
FROM ArchivosAdjuntos
WHERE cod_operario = ?
AND pestaña = ?
AND tipo_documento IN ($placeholders)
AND obligatorio = 1
AND (cod_contrato_asociado = ? OR ? IS NULL)
");

        $params = array_merge([$codOperario, $pestaña], $tipos, [$codContrato, $codContrato]);
        $stmt->execute($params);
        $result = $stmt->fetch();

        $completados += $result['completados'] ?? 0;
    }

    if ($totalObligatorios == 0) {
        return ['porcentaje' => 100, 'completados' => 0, 'total' => 0];
    }

    $porcentaje = round(($completados / $totalObligatorios) * 100);

    return [
        'porcentaje' => $porcentaje,
        'completados' => $completados,
        'total' => $totalObligatorios
    ];
}

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
    if ($_POST['accion_adjunto'] == 'agregar' && !empty($_FILES['archivo_adjunto']['name'])) {

        // DEBUG: Verificar datos del POST
        error_log("Datos recibidos del formulario adjunto:");
        error_log(print_r($_POST, true));

        $resultado = agregarArchivoAdjunto([
            'cod_operario' => $codOperario,
            'pestaña' => $_POST['pestaña_adjunto'],
            'tipo_documento' => $_POST['tipo_documento'] ?? null,
            'descripcion' => $_POST['descripcion_adjunto'] ?? '',
            'cod_usuario_subio' => $_SESSION['usuario_id']
        ], $_FILES['archivo_adjunto']);

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
    JOIN Operarios o ON a.cod_usuario_subio = o.CodOperario
    LEFT JOIN Contratos c ON a.cod_contrato_asociado = c.CodContrato
    LEFT JOIN TipoContrato tc ON c.cod_tipo_contrato = tc.CodTipoContrato
    LEFT JOIN AsignacionNivelesCargos anc ON a.cod_adendum_asociado = anc.CodAsignacionNivelesCargos
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN CategoriasOperarios co ON anc.CodNivelesCargos = co.idCategoria
    WHERE a.cod_operario = ? AND a.pestaña = ?
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
function agregarArchivoAdjunto($datos, $archivo)
{
    global $conn;

    try {
        // Validar que sea un PDF
        $tipoArchivo = $archivo['type'];
        if ($tipoArchivo != 'application/pdf') {
            return ['exito' => false, 'mensaje' => 'Solo se permiten archivos PDF'];
        }

        // Validar tamaño (máximo 10MB)
        $tamañoMaximo = 10 * 1024 * 1024;
        if ($archivo['size'] > $tamañoMaximo) {
            return ['exito' => false, 'mensaje' => 'El archivo no puede ser mayor a 10MB'];
        }

        // Determinar si se debe asociar a un contrato o adendum
        $codContratoAsociado = null;
        $codAdendumAsociado = null;
        $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];

        if (in_array($datos['pestaña'], $pestañasConContrato)) {
            $contratoActual = obtenerContratoActual($datos['cod_operario']);
            if ($contratoActual) {
                $codContratoAsociado = $contratoActual['CodContrato'];

                // Si es la pestaña de adendums y tenemos un ID de adendum
                if ($datos['pestaña'] == 'adendums' && !empty($datos['cod_adendum_asociado'])) {
                    $codAdendumAsociado = $datos['cod_adendum_asociado'];
                } elseif ($datos['pestaña'] == 'adendums') {
                    // Si no se proporciona ID de adendum, buscar el último adendum activo automáticamente
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

        // Validar tipo de documento si se proporciona - CORRECCIÓN APLICADA
        if (!empty($datos['tipo_documento'])) {
            $tiposPermitidos = obtenerTiposDocumentosPorPestaña($datos['pestaña']);
            $todosTipos = array_merge(
                array_keys($tiposPermitidos['obligatorios']),
                array_keys($tiposPermitidos['opcionales'])
            );

            // PERMITIR SIEMPRE EL TIPO "otro" INDEPENDIENTEMENTE DE LA CONFIGURACIÓN
            if (!in_array($datos['tipo_documento'], $todosTipos) && $datos['tipo_documento'] !== 'otro') {
                return ['exito' => false, 'mensaje' => 'Tipo de documento no válido para esta pestaña'];
            }

            // Verificar si ya existe un archivo del mismo tipo (para obligatorios)
            // EXCLUIR "otro" DE ESTA VERIFICACIÓN PARA PERMITIR MÚLTIPLES ARCHIVOS "otro"
            if (
                in_array($datos['tipo_documento'], array_keys($tiposPermitidos['obligatorios'])) && $datos['tipo_documento'] !==
                'otro'
            ) {
                $stmtCheck = $conn->prepare("
    SELECT COUNT(*) FROM ArchivosAdjuntos
    WHERE cod_operario = ?
    AND pestaña = ?
    AND tipo_documento = ?
    AND (cod_contrato_asociado = ? OR ? IS NULL)
    ");
                $stmtCheck->execute([
                    $datos['cod_operario'],
                    $datos['pestaña'],
                    $datos['tipo_documento'],
                    $codContratoAsociado,
                    $codContratoAsociado
                ]);

                if ($stmtCheck->fetchColumn() > 0) {
                    return [
                        'exito' => false,
                        'mensaje' => 'Ya existe un archivo de este tipo. Elimine el existente antes de subir uno
    nuevo.'
                    ];
                }
            }
        }

        // Resto del código sin cambios...
        // Crear directorio si no existe
        $directorio = "../../uploads/adjuntos/" . $datos['cod_operario'] . "/";
        if (!file_exists($directorio)) {
            mkdir($directorio, 0777, true);
        }

        // Generar nombre único para el archivo
        $nombreArchivo = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $archivo['name']);
        $rutaCompleta = $directorio . $nombreArchivo;

        // Mover el archivo
        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            return ['exito' => false, 'mensaje' => 'Error al subir el archivo'];
        }

        // Determinar si es obligatorio
        $obligatorio = 0;
        $categoria = 'opcional';

        if (!empty($datos['tipo_documento'])) {
            $tiposPermitidos = obtenerTiposDocumentosPorPestaña($datos['pestaña']);
            if (
                in_array($datos['tipo_documento'], array_keys($tiposPermitidos['obligatorios'])) && $datos['tipo_documento'] !==
                'otro'
            ) {
                $obligatorio = 1;
                $categoria = 'obligatorio';
            }
        }

        // Guardar en la base de datos
        $stmt = $conn->prepare("
    INSERT INTO ArchivosAdjuntos
    (cod_operario, cod_contrato_asociado, cod_adendum_asociado, pestaña, tipo_documento, obligatorio, categoria,
    nombre_archivo, descripcion, tamaño, ruta_archivo, cod_usuario_subio)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        $stmt->execute([
            $datos['cod_operario'],
            $codContratoAsociado,
            $codAdendumAsociado,
            $datos['pestaña'],
            $datos['tipo_documento'] ?? null,
            $obligatorio,
            $categoria,
            $archivo['name'],
            $datos['descripcion'] ?? '',
            $archivo['size'],
            $rutaCompleta,
            $datos['cod_usuario_subio']
        ]);

        return ['exito' => true, 'mensaje' => 'Archivo subido correctamente'];
    } catch (Exception $e) {
        error_log("Error en agregarArchivoAdjunto: " . $e->getMessage());
        return ['exito' => false, 'mensaje' => 'Error al subir el archivo: ' . $e->getMessage()];
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
 * Obtiene el nombre amigable de la pestaña
 */
function obtenerNombrePestaña($pestaña)
{
    $nombres = [
        'datos-personales' => 'Datos Personales',
        'inss' => 'INSS',
        'contrato' => 'Contrato',
        'contactos-emergencia' => 'Contactos de Emergencia',
        'salario' => 'Salario',
        'movimientos' => 'Movimientos',
        'categoria' => 'Categoría',
        'adendums' => 'Adendums',
        'expediente-digital' => 'Expediente Digital'
    ];

    return $nombres[$pestaña] ?? ucfirst(str_replace('-', ' ', $pestaña));
}

/**
 * Verifica el estado global de documentos obligatorios
 */
function verificarEstadoGlobalDocumentos($codOperario)
{
    $pestañasRevisar = ['datos-personales', 'inss', 'contrato'];
    $totalObligatorios = 0;
    $completos = 0;

    foreach ($pestañasRevisar as $pestaña) {
        $estado = verificarEstadoDocumentosObligatorios($codOperario, $pestaña);

        if ($estado !== 'no_aplica') {
            $totalObligatorios++;

            if ($estado === 'completo') {
                $completos++;
            }
        }
    }

    if ($totalObligatorios == 0)
        return 'no_aplica';
    if ($completos == $totalObligatorios)
        return 'completo';
    if ($completos == 0)
        return 'pendiente';
    return 'parcial';
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
 * Verifica si existen archivos adjuntos para adendums
 */
function verificarArchivosAdendum($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM ArchivosAdjuntos
    WHERE cod_operario = ?
    AND pestaña = 'adendums'
    AND obligatorio = 1
    ");
    $stmt->execute([$codOperario]);
    $result = $stmt->fetch();

    return $result['total'] > 0;
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
<!DOCTYPE html>
<html lang="es">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Colaborador</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/editar_colaborador.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoId); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Datos del Colaborador'); ?>
            <div class="container-fluid">

                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['exito'] ?>
                        <?php unset($_SESSION['exito']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="form-container">
                    <!-- Sección de Perfil del Colaborador -->
                    <div class="tabs">
                        <!-- Foto y nombre del colaborador -->
                        <div class="perfil-colaborador">
                            <div class="foto-perfil-container">
                                <form id="formFotoPerfil" method="POST"
                                    action="editar_colaborador.php?id=<?= $codOperario ?>" enctype="multipart/form-data"
                                    style="position: relative;">
                                    <input type="hidden" name="pestaña" value="datos-personales">
                                    <input type="hidden" name="accion" value="guardar_foto_perfil">
                                    <input type="file" id="inputFotoPerfil" name="foto_perfil" accept="image/*"
                                        style="display: none;">

                                    <div class="foto-perfil" style="position: relative; cursor: pointer;">
                                        <?php if (!empty($colaborador['foto_perfil'])): ?>
                                            <img src="../../<?= htmlspecialchars($colaborador['foto_perfil']) ?>"
                                                alt="Foto de perfil" class="foto-img"
                                                onclick="abrirModalVerFoto('../../<?= htmlspecialchars($colaborador['foto_perfil']) ?>')">
                                        <?php else: ?>
                                            <div class="iniciales">
                                                <?= strtoupper(substr($colaborador['Nombre'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="edit-icon"
                                            onclick="event.stopPropagation(); document.getElementById('inputFotoPerfil').click()"
                                            title="Cambiar foto de perfil" style="cursor: pointer;">
                                            <i class="fas fa-pencil-alt"></i>
                                        </div>
                                        <?php if (!empty($colaborador['foto_perfil'])): ?>
                                            <div class="view-icon"
                                                onclick="abrirModalVerFoto('../../<?= htmlspecialchars($colaborador['foto_perfil']) ?>')"
                                                title="Ver foto completa"
                                                style="position: absolute; bottom: 10px; left: 10px; background: rgba(14, 84, 76, 0.9); color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transition: opacity 0.3s;">
                                                <i class="fas fa-eye"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <div class="info-colaborador">
                                <h3 style="text-align:center;" class="nombre-completo">
                                    <?= htmlspecialchars($colaborador['Nombre'] . ' ' . $colaborador['Apellido'] . ' ' . ($colaborador['Apellido2'] ?? '')) ?>
                                </h3>
                                <p style="display:none;" class="cargo-actual">
                                    <?= htmlspecialchars($colaborador['cargo_nombre'] ?? 'Sin cargo definido') ?>
                                </p>
                                <p style="visibility:hidden;" class="codigo-operario">Código:
                                    <?= htmlspecialchars($colaborador['CodOperario']) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Pestañas de navegación -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=datos-personales"
                                class="tab-button <?= $pestaña_activa == 'datos-personales' ? 'active' : '' ?>">Datos
                                Personales</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=datos-contacto"
                                class="tab-button <?= $pestaña_activa == 'datos-contacto' ? 'active' : '' ?>">Datos de
                                Contacto</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=contactos-emergencia"
                                class="tab-button <?= $pestaña_activa == 'contactos-emergencia' ? 'active' : '' ?>">Contactos
                                de
                                Emergencia</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=contrato"
                                class="tab-button <?= $pestaña_activa == 'contrato' ? 'active' : '' ?>">Contrato</a>
                        <?php endif; ?>
                        <?php if (verificarAccesoCargo([0])): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=salario"
                                class="tab-button <?= $pestaña_activa == 'salario' ? 'active' : '' ?>">Salario</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=inss"
                                class="tab-button <?= $pestaña_activa == 'inss' ? 'active' : '' ?>">INSS</a>
                        <?php endif; ?>
                        <?php if (verificarAccesoCargo([0])): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=movimientos"
                                class="tab-button <?= $pestaña_activa == 'movimientos' ? 'active' : '' ?>">Movimientos</a>
                        <?php endif; ?>
                        <?php if (verificarAccesoCargo([0])): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=categoria"
                                class="tab-button <?= $pestaña_activa == 'categoria' ? 'active' : '' ?>">Categoría</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=adendums"
                                class="tab-button <?= $pestaña_activa == 'adendums' ? 'active' : '' ?>">Adenda de Contrato y
                                Movimientos</a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=expediente-digital"
                                class="tab-button <?= $pestaña_activa == 'expediente-digital' ? 'active' : '' ?>">
                                Expediente Digital
                                <?php
                                $estadoExpediente = verificarEstadoDocumentosObligatorios($codOperario, 'global');
                                echo obtenerIconoEstadoDocumentos($estadoExpediente);
                                ?>
                            </a>
                        <?php endif; ?>
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <a href="?id=<?= $codOperario ?>&pestaña=bitacora"
                                class="tab-button <?= $pestaña_activa == 'bitacora' ? 'active' : '' ?>">Bitácora</a>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content">
                        <!-- Pestaña de Datos Personales -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="datos-personales"
                                class="tab-pane <?= $pestaña_activa == 'datos-personales' ? 'active' : '' ?>">
                                <!-- Sección de Documentos Obligatorios Faltantes -->
                                <div
                                    style="margin: 20px 0; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffeaa7;">
                                    <h4 style="color: #856404; margin-bottom: 15px;">
                                        <i class="fas fa-exclamation-triangle"></i> Documentos Obligatorios Faltantes -
                                        <?= obtenerNombrePestaña($pestaña_activa) ?>
                                    </h4>

                                    <?php
                                    $documentosFaltantesPestana = obtenerDocumentosFaltantesPestana($codOperario, $pestaña_activa);
                                    ?>

                                    <?php if (!empty($documentosFaltantesPestana)): ?>
                                        <ul style="color: #856404; margin: 0; padding-left: 20px;">
                                            <?php foreach ($documentosFaltantesPestana as $documento): ?>
                                                <li><?= htmlspecialchars($documento) ?></li>
                                            <?php endforeach; ?>
                                        </ul>

                                        <p style="color: #856404; margin: 15px 0 0 0; font-style: italic;">
                                            <i class="fas fa-info-circle"></i> Estos documentos deben ser subidos para
                                            completar la
                                            información.
                                        </p>
                                    <?php else: ?>
                                        <div style="color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;">
                                            <i class="fas fa-check-circle"></i> Todos los documentos obligatorios están
                                            completos para
                                            esta pestaña.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <form method="POST" action="">
                                    <input type="hidden" name="accion" value="guardar_datos_personales">
                                    <input type="hidden" name="pestaña" value="datos-personales">

                                    <div class="readonly-info">
                                        <p><strong>Código:</strong> <?= htmlspecialchars($colaborador['CodOperario']) ?>
                                        </p>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="nombre">Primer Nombre</label>
                                                <input type="text" id="nombre" name="nombre" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Nombre'] ?? '') ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label for="apellido">Primer Apellido</label>
                                                <input type="text" id="apellido" name="apellido" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Apellido'] ?? '') ?>"
                                                    required>
                                            </div>

                                            <div class="form-group">
                                                <label for="cedula">Cédula</label>
                                                <input type="text" id="cedula" name="cedula" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Cedula'] ?? '') ?>"
                                                    placeholder="Ej: XXX-XXXXXX-XXXX"
                                                    pattern="[0-9]{3}-[0-9]{6}-[0-9]{4}[A-Za-z]?"
                                                    title="Formato: 001-234567-8910A">
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="nombre2">Segundo Nombre</label>
                                                <input type="text" id="nombre2" name="nombre2" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Nombre2'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="apellido2">Segundo Apellido</label>
                                                <input type="text" id="apellido2" name="apellido2" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Apellido2'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="genero">Género</label>
                                                <select id="genero" name="genero" class="form-control">
                                                    <option value="">Seleccionar...</option>
                                                    <option value="M" <?= (isset($colaborador['Genero']) && $colaborador['Genero'] == 'M') ? 'selected' : '' ?>>Masculino
                                                    </option>
                                                    <option value="F" <?= (isset($colaborador['Genero']) && $colaborador['Genero'] == 'F') ? 'selected' : '' ?>>Femenino
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="cumpleanos">Fecha de Cumpleaños</label>
                                        <input type="date" id="cumpleanos" name="cumpleanos" class="form-control"
                                            value="<?= !empty($colaborador['Cumpleanos']) ? date('Y-m-d', strtotime($colaborador['Cumpleanos'])) : '' ?>">
                                    </div>

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="usuario">Usuario</label>
                                                <input type="text" id="usuario" name="usuario" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['usuario'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="clave">Clave <small style="color: #6c757d;">(dejar vacío si
                                                        no desea
                                                        cambiar)</small></label>
                                                <div style="display: flex; align-items: center;">
                                                    <input type="password" id="clave" name="clave" class="form-control"
                                                        value="<?= htmlspecialchars($colaborador['clave'] ?? '') ?>"
                                                        style="flex: 1; margin-right: 10px;">
                                                    <button type="button" id="toggleClave"
                                                        style="background: #0E544C; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer;">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-submit">Guardar Cambios</button>
                                </form>

                                <!-- Sección de Cuentas Bancarias -->
                                <div style="margin-top: 40px; border-top: 2px solid #0E544C; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #0E544C; margin: 0;">Cuentas Bancarias</h3>
                                        <button type="button" class="btn-submit" onclick="abrirModalCuenta()"
                                            style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar
                                        </button>
                                    </div>

                                    <?php if (count($cuentasBancarias) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #0E544C; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Número Cuenta</th>
                                                        <th style="padding: 10px; text-align: left;">Titular</th>
                                                        <th style="padding: 10px; text-align: left;">Banco</th>
                                                        <th style="padding: 10px; text-align: left;">Moneda</th>
                                                        <th style="padding: 10px; text-align: left;">Desde</th>
                                                        <th style="padding: 10px; text-align: center; display:none;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($cuentasBancarias as $cuenta): ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cuenta['numero_cuenta']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cuenta['titular']) ?>
                                                            </td>
                                                            <td style="padding: 10px;"><?= htmlspecialchars($cuenta['banco']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cuenta['moneda']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($cuenta['desde']) ? date('d/m/Y', strtotime($cuenta['desde'])) : '' ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center; display:none;">
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarCuenta(<?= $cuenta['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_cuenta" value="eliminar">
                                                                    <input type="hidden" name="id_cuenta"
                                                                        value="<?= $cuenta['id'] ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar esta cuenta bancaria?')"
                                                                        class="btn-accion btn-eliminar">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay cuentas
                                            bancarias
                                            registradas</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Sección de Archivos Adjuntos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                        <button type="button" class="btn-submit"
                                            onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar Archivo
                                        </button>
                                    </div>

                                    <?php if (count($archivosAdjuntos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Nombre</th>
                                                        <th style="padding: 10px; text-align: left;">Descripción</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Subido por</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                        <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archivosAdjuntos as $archivo):
                                                        // Formatear tamaño del archivo
                                                        $tamaño = $archivo['tamaño'];
                                                        if ($tamaño < 1024) {
                                                            $tamañoFormateado = $tamaño . ' B';
                                                        } elseif ($tamaño < 1048576) {
                                                            $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                        } else {
                                                            $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                if (!empty($archivo['tipo_documento'])) {
                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                    echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                    if ($archivo['obligatorio']) {
                                                                        echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                // Mostrar información del contrato usando codigo_manual_contrato
                                                                $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                    ?>
                                                                    <span
                                                                        style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                    <br>
                                                                    <small style="color: #6c757d;">
                                                                        <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                        <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                            - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                        <?php else: ?>
                                                                            (Activo)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                        <br>
                                                                        <small
                                                                            style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: #6c757d; font-style: italic;">
                                                                        <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                    target="_blank" class="btn-accion btn-editar"
                                                                    title="Ver archivo">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                    <input type="hidden" name="id_adjunto"
                                                                        value="<?= $archivo['id'] ?>">
                                                                    <input type="hidden" name="pestaña_adjunto"
                                                                        value="<?= $pestaña_activa ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                        class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                            adjuntos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Datos de Contacto -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="datos-contacto"
                                class="tab-pane <?= $pestaña_activa == 'datos-contacto' ? 'active' : '' ?>">
                                <form method="POST" action="">
                                    <input type="hidden" name="accion" value="guardar_datos_contacto">
                                    <input type="hidden" name="pestaña" value="datos-contacto">

                                    <div class="form-group">
                                        <label for="direccion">Dirección</label>
                                        <textarea id="direccion" name="direccion" class="form-control"
                                            rows="3"><?= htmlspecialchars($colaborador['direccion'] ?? '') ?></textarea>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="ciudad">Ciudad</label>
                                                <input type="text" id="ciudad" name="ciudad" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Ciudad'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="celular">Teléfono Móvil (celular)</label>
                                                <input type="text" id="celular" name="celular" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['Celular'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="email_personal">Email Personal</label>
                                                <input type="email" id="email_personal" name="email_personal"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['email_personal'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="telefono_casa">Teléfono de Casa</label>
                                                <input type="text" id="telefono_casa" name="telefono_casa"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['telefono_casa'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="telefono_corporativo">Teléfono Corporativo</label>
                                                <input type="text" id="telefono_corporativo" name="telefono_corporativo"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['telefono_corporativo'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="email_trabajo">Email de Trabajo</label>
                                                <input type="email" id="email_trabajo" name="email_trabajo"
                                                    class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['email_trabajo'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-submit">Guardar Cambios</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Contactos de Emergencia -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="contactos-emergencia"
                                class="tab-pane <?= $pestaña_activa == 'contactos-emergencia' ? 'active' : '' ?>">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="color: #0E544C; margin: 0;">Contactos de Emergencia</h3>
                                    <button type="button" class="btn-submit" onclick="abrirModalContacto()"
                                        style="margin: 0;">
                                        <i class="fas fa-plus"></i> Agregar
                                    </button>
                                </div>

                                <?php if (count($contactosEmergencia) > 0): ?>
                                    <div style="overflow-x: auto;">
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <thead>
                                                <tr style="background-color: #0E544C; color: white;">
                                                    <th style="padding: 10px; text-align: left;">Nombre</th>
                                                    <th style="padding: 10px; text-align: left;">Parentesco</th>
                                                    <th style="padding: 10px; text-align: left;">Teléfono Móvil</th>
                                                    <th style="padding: 10px; text-align: left;">Teléfono Casa</th>
                                                    <th style="padding: 10px; text-align: left;">Teléfono Trabajo</th>
                                                    <th style="padding: 10px; text-align: center;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($contactosEmergencia as $contacto): ?>
                                                    <tr style="border-bottom: 1px solid #ddd;">
                                                        <td style="padding: 10px;">
                                                            <?= htmlspecialchars($contacto['nombre_contacto']) ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= htmlspecialchars($contacto['parentesco']) ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= htmlspecialchars($contacto['telefono_movil']) ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= htmlspecialchars($contacto['telefono_casa']) ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= htmlspecialchars($contacto['telefono_trabajo']) ?>
                                                        </td>
                                                        <td style="padding: 10px; text-align: center;">
                                                            <button type="button" class="btn-accion btn-editar"
                                                                onclick="editarContacto(<?= $contacto['id'] ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" action="" style="display: inline;">
                                                                <input type="hidden" name="accion_contacto" value="eliminar">
                                                                <input type="hidden" name="id_contacto"
                                                                    value="<?= $contacto['id'] ?>">
                                                                <button type="submit"
                                                                    onclick="return confirm('¿Está seguro de eliminar este contacto de emergencia?')"
                                                                    class="btn-accion btn-eliminar">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p style="text-align: center; color: #6c757d; padding: 20px;">No hay contactos de
                                        emergencia
                                        registrados</p>
                                <?php endif; ?>

                                <!-- Sección de Archivos Adjuntos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                        <button type="button" class="btn-submit"
                                            onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar Archivo
                                        </button>
                                    </div>

                                    <?php if (count($archivosAdjuntos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Nombre</th>
                                                        <th style="padding: 10px; text-align: left;">Descripción</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Subido por</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                        <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archivosAdjuntos as $archivo):
                                                        // Formatear tamaño del archivo
                                                        $tamaño = $archivo['tamaño'];
                                                        if ($tamaño < 1024) {
                                                            $tamañoFormateado = $tamaño . ' B';
                                                        } elseif ($tamaño < 1048576) {
                                                            $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                        } else {
                                                            $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                if (!empty($archivo['tipo_documento'])) {
                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                    echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                    if ($archivo['obligatorio']) {
                                                                        echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                // Mostrar información del contrato usando codigo_manual_contrato
                                                                $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                    ?>
                                                                    <span
                                                                        style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                    <br>
                                                                    <small style="color: #6c757d;">
                                                                        <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                        <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                            - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                        <?php else: ?>
                                                                            (Activo)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                        <br>
                                                                        <small
                                                                            style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: #6c757d; font-style: italic;">
                                                                        <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                    target="_blank" class="btn-accion btn-editar"
                                                                    title="Ver archivo">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                    <input type="hidden" name="id_adjunto"
                                                                        value="<?= $archivo['id'] ?>">
                                                                    <input type="hidden" name="pestaña_adjunto"
                                                                        value="<?= $pestaña_activa ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                        class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                            adjuntos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Contrato -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="contrato" class="tab-pane <?= $pestaña_activa == 'contrato' ? 'active' : '' ?>">
                                <!-- Sección de Documentos Obligatorios Faltantes -->
                                <div
                                    style="margin: 20px 0; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffeaa7;">
                                    <h4 style="color: #856404; margin-bottom: 15px;">
                                        <i class="fas fa-exclamation-triangle"></i> Documentos Obligatorios Faltantes -
                                        <?= obtenerNombrePestaña($pestaña_activa) ?>
                                    </h4>

                                    <?php
                                    $documentosFaltantesPestana = obtenerDocumentosFaltantesPestana($codOperario, $pestaña_activa);
                                    ?>

                                    <?php if (!empty($documentosFaltantesPestana)): ?>
                                        <ul style="color: #856404; margin: 0; padding-left: 20px;">
                                            <?php foreach ($documentosFaltantesPestana as $documento): ?>
                                                <li><?= htmlspecialchars($documento) ?></li>
                                            <?php endforeach; ?>
                                        </ul>

                                        <p style="color: #856404; margin: 15px 0 0 0; font-style: italic;">
                                            <i class="fas fa-info-circle"></i> Estos documentos deben ser subidos para
                                            completar la
                                            información.
                                        </p>
                                    <?php else: ?>
                                        <div style="color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;">
                                            <i class="fas fa-check-circle"></i> Todos los documentos obligatorios están
                                            completos para
                                            esta pestaña.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php
                                // Obtener datos del contrato actual
                                $contratoActual = obtenerContratoActual($codOperario);
                                $estaFinalizado = $contratoActual ? contratoEstaFinalizado($contratoActual) : false;
                                $estaActivo = $contratoActual ? contratoEstaActivo($contratoActual) : false;
                                $asignacionCargoActual = obtenerAsignacionCargoActual($codOperario);
                                $categoriaActual = obtenerCategoriaActual($codOperario);
                                $salarioActual = obtenerSalarioActual($codOperario);

                                // NUEVO: Determinar si debemos mostrar el formulario para nuevo contrato
                                $mostrarFormularioNuevoContrato = !$contratoActual || $estaFinalizado;
                                ?>

                                <?php if ($contratoActual && $estaActivo): ?>
                                    <div style="margin-bottom: 20px;">
                                        <h3 style="color: #0E544C; margin-bottom: 15px;">Información de Contrato Actual</h3>
                                        <div class="readonly-info">
                                            <p><strong>Estado:</strong> <span style="color: green;">Contrato Activo</span>
                                            </p>
                                            <p><strong>Fecha Inicio:</strong>
                                                <?= !empty($contratoActual['inicio_contrato']) ? date('d/m/Y', strtotime($contratoActual['inicio_contrato'])) : 'No definida' ?>
                                            </p>
                                            <p><strong>Tipo de Contrato:</strong>
                                                <?= htmlspecialchars(obtenerNombreTipoContrato($contratoActual['cod_tipo_contrato'])) ?>
                                            </p>
                                            <p><strong>Cargo:</strong>
                                                <?= htmlspecialchars(obtenerNombreCargo($asignacionCargoActual['CodNivelesCargos'])) ?>
                                            </p>
                                            <p><strong>Salario:</strong>
                                                <?= $salarioActual ? number_format($salarioActual['monto'], 2) : 'No definido' ?>
                                            </p>
                                            <?php if (!empty($contratoActual['fin_contrato']) && $contratoActual['fin_contrato'] != '0000-00-00'): ?>
                                                <p><strong>Fecha Fin:</strong>
                                                    <?= date('d/m/Y', strtotime($contratoActual['fin_contrato'])) ?></p>
                                                <p><strong>Tiempo Restante:</strong>
                                                    <?= calcularTiempoRestanteContrato($contratoActual['fin_contrato'], $estaActivo) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php elseif ($contratoActual && $estaFinalizado): ?>
                                    <div style="margin-bottom: 20px;">
                                        <div class="readonly-info" style="background-color: #f8d7da; border-color: #f5c6cb;">
                                            <p><strong>Estado:</strong> <span style="color: #721c24;">Contrato
                                                    Finalizado</span></p>
                                            <p><strong>Fecha Salida:</strong>
                                                <?= !empty($contratoActual['fecha_salida']) ? date('d/m/Y', strtotime($contratoActual['fecha_salida'])) : 'No definida' ?>
                                            </p>
                                            <p><strong>Motivo:</strong>
                                                <?= htmlspecialchars($contratoActual['motivo'] ?? 'No especificado') ?></p>
                                            <p><strong>Puede crear un nuevo contrato:</strong> Complete el formulario
                                                inferior para
                                                registrar un nuevo contrato.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-bottom: 20px;">
                                        <div class="readonly-info" style="background-color: #fff3cd; border-color: #ffeaa7;">
                                            <p><strong>Estado:</strong> <span style="color: #856404;">Sin contrato
                                                    activo</span></p>
                                            <p>Este colaborador no tiene un contrato activo registrado en el sistema.
                                                Complete el
                                                formulario para crear uno nuevo.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- FORMULARIO DE CONTRATO -->
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="pestaña" value="contrato">

                                    <!-- NUEVO: Cambiar el valor de accion_contrato según si es nuevo o edición -->
                                    <?php if ($mostrarFormularioNuevoContrato): ?>
                                        <input type="hidden" name="accion_contrato" value="guardar">
                                        <h3 style="color: #0E544C; margin-bottom: 15px;">Nuevo Contrato</h3>
                                    <?php else: ?>
                                        <input type="hidden" name="accion_contrato" value="guardar">
                                        <input type="hidden" name="id_contrato" value="<?= $contratoActual['CodContrato'] ?>">
                                        <h3 style="color: #0E544C; margin-bottom: 15px;">Editar Contrato Actual</h3>
                                    <?php endif; ?>

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="codigo_manual_contrato">Código de Contrato</label>
                                                <input type="text" id="codigo_manual_contrato" name="codigo_manual_contrato"
                                                    class="form-control"
                                                    value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? htmlspecialchars($contratoActual['codigo_manual_contrato'] ?? '') : '' ?>"
                                                    onblur="validarCodigoContrato(this.value)">
                                                <div id="codigo-contrato-error" class="text-danger"
                                                    style="display: none; font-size: 12px; margin-top: 5px;">
                                                    ⚠️ Este código de contrato ya existe. Debe usar un código único.
                                                </div>
                                                <div id="codigo-contrato-success" class="text-success"
                                                    style="display: none; font-size: 12px; margin-top: 5px;">
                                                    ✅ Código disponible
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label for="cod_cargo">Cargo *</label>
                                                <select id="cod_cargo" name="cod_cargo" class="form-control" required
                                                    onchange="actualizarCategoriaYMostrar()">
                                                    <option value="">Seleccionar cargo...</option>
                                                    <?php
                                                    $cargos = obtenerTodosCargos();
                                                    foreach ($cargos as $cargo):
                                                        // Determinar la categoría sugerida para este cargo
                                                        $categoriaSugerida = '';
                                                        $idCategoriaSugerida = '';

                                                        if ($cargo['CodNivelesCargos'] == 2) {
                                                            $categoriaSugerida = ' (Categoría: Training)';
                                                            $idCategoriaSugerida = 5; // ID de la categoría Operario en CategoriasOperarios
                                                        } elseif ($cargo['CodNivelesCargos'] == 5) {
                                                            $categoriaSugerida = ' (Categoría: Líder)';
                                                            $idCategoriaSugerida = 1; // ID de la categoría Líder en CategoriasOperarios
                                                        }
                                                        ?>
                                                        <option value="<?= $cargo['CodNivelesCargos'] ?>"
                                                            data-categoria="<?= $idCategoriaSugerida ?>"
                                                            <?= (!$mostrarFormularioNuevoContrato && $asignacionCargoActual && $asignacionCargoActual['CodNivelesCargos'] == $cargo['CodNivelesCargos']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($cargo['Nombre']) ?>
                                                            <?= $categoriaSugerida ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div id="infoCategoria" class="categoria-info" style="display: none;">
                                                    <i class="fas fa-info-circle"></i> <span id="textoCategoria">Categoría
                                                        asignada
                                                        automáticamente</span>
                                                </div>
                                            </div>

                                            <div class="form-group" style="display: none;">
                                                <label for="id_categoria">Categoría</label>
                                                <select id="id_categoria" name="id_categoria" class="form-control">
                                                    <option value="">Seleccionar categoría...</option>
                                                    <?php
                                                    $categorias = obtenerTodasCategorias();
                                                    foreach ($categorias as $categoria): ?>
                                                        <option value="<?= $categoria['idCategoria'] ?>"
                                                            <?= (!$mostrarFormularioNuevoContrato && $categoriaActual && $categoriaActual['idCategoria'] == $categoria['idCategoria']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="ciudad">Departamento/Ciudad de Contrato *</label>
                                                <input type="text" id="ciudad" name="ciudad" class="form-control"
                                                    value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? htmlspecialchars($contratoActual['ciudad']) : '' ?>"
                                                    required>
                                            </div>

                                            <div class="form-group">
                                                <label for="inicio_contrato">Fecha de Inicio *</label>
                                                <input type="date" id="inicio_contrato" name="inicio_contrato"
                                                    class="form-control"
                                                    value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? $contratoActual['inicio_contrato'] : date('Y-m-d') ?>"
                                                    required>
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="cod_tipo_contrato">Tipo de Contrato *</label>
                                                <select id="cod_tipo_contrato" name="cod_tipo_contrato" class="form-control"
                                                    required>
                                                    <option value="">Seleccionar tipo de contrato...</option>
                                                    <?php
                                                    $tiposContrato = obtenerTiposContrato();
                                                    foreach ($tiposContrato as $tipo): ?>
                                                        <option value="<?= $tipo['CodTipoContrato'] ?>"
                                                            <?= (!$mostrarFormularioNuevoContrato && $contratoActual && $contratoActual['cod_tipo_contrato'] == $tipo['CodTipoContrato']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($tipo['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-col">
                                                <div class="form-group">
                                                    <label for="monto_salario">Salario Básico *</label>
                                                    <input type="number" id="monto_salario" name="monto_salario"
                                                        class="form-control" step="0.01" min="0"
                                                        value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? ($contratoActual['salario_inicial'] ?? '') : '' ?>"
                                                        required>
                                                </div>


                                            </div>

                                            <div class="form-group">
                                                <label for="sucursal">Tienda / Área *</label>
                                                <select id="sucursal" name="sucursal" class="form-control" required>
                                                    <option value="">Seleccionar sucursal...</option>
                                                    <?php
                                                    $sucursales = obtenerTodasSucursales();
                                                    foreach ($sucursales as $sucursal): ?>
                                                        <option value="<?= $sucursal['codigo'] ?>"
                                                            <?= (!$mostrarFormularioNuevoContrato && $contratoActual && $contratoActual['cod_sucursal_contrato'] == $sucursal['codigo']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="frecuencia_pago">Frecuencia de Pago *</label>
                                                <select id="frecuencia_pago" name="frecuencia_pago" class="form-control"
                                                    required>
                                                    <option value="quincenal" <?= (!$mostrarFormularioNuevoContrato && $contratoActual && ($contratoActual['frecuencia_pago'] ?? 'quincenal') == 'quincenal') ? 'selected' : '' ?>>Quincenal
                                                    </option>
                                                    <option value="mensual" <?= (!$mostrarFormularioNuevoContrato && $contratoActual && ($contratoActual['frecuencia_pago'] ?? '') == 'mensual') ? 'selected' : '' ?>>Mensual</option>
                                                </select>
                                            </div>

                                            <div class="form-group" style="display: none;">
                                                <label for="foto_contrato">Foto del Contrato</label>
                                                <input type="file" id="foto_contrato" name="foto_contrato"
                                                    class="form-control" accept="image/*,.pdf">
                                                <?php if (!$mostrarFormularioNuevoContrato && $contratoActual && !empty($contratoActual['foto'])): ?>
                                                    <small style="color: green;">Ya existe un archivo subido:
                                                        <?= htmlspecialchars(basename($contratoActual['foto'])) ?></small>
                                                <?php endif; ?>
                                            </div>

                                            <div class="form-group" id="grupo_fecha_fin_contrato">
                                                <label for="fin_contrato">
                                                    Fecha Fin de Contrato
                                                    <small style="color: #6c757d;">
                                                        (solo para contratos temporales)
                                                    </small>
                                                </label>
                                                <input type="date" id="fin_contrato" name="fin_contrato"
                                                    class="form-control"
                                                    value="<?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? ($contratoActual['fin_contrato'] ?? '') : '' ?>"
                                                    <?= (!$mostrarFormularioNuevoContrato && $contratoActual && $contratoActual['cod_tipo_contrato'] != 1) ? 'disabled' : '' ?>>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="observaciones">Observaciones</label>
                                        <textarea id="observaciones" name="observaciones" class="form-control"
                                            rows="3"><?= (!$mostrarFormularioNuevoContrato && $contratoActual) ? htmlspecialchars($contratoActual['observaciones']) : '' ?></textarea>
                                    </div>

                                    <button type="submit" class="btn-submit">
                                        <?= $mostrarFormularioNuevoContrato ? 'Crear Nuevo Contrato' : 'Actualizar Contrato' ?>
                                    </button>

                                    <!-- Sección de Terminación de Contrato -->
                                    <?php if (!$mostrarFormularioNuevoContrato && $contratoActual): ?>
                                        <?php if (empty($contratoActual['fin_contrato']) || $contratoActual['fin_contrato'] >= date('Y-m-d')): ?>
                                            <button type="button" class="btn-submit" onclick="abrirModalTerminacion()"
                                                style="background-color: #dc3545; margin-left: 10px;">
                                                <i class="fas fa-times"></i> Finalizar Contrato
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-style: italic;">Contrato ya finalizado</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </form>

                                <!-- Sección de Historial de Contratos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">
                                            Historial de Contratos
                                            <span style="font-size: 0.8em; color: #0E544C;">
                                                (Total: <?= count($historialContratos) ?>)
                                            </span>
                                        </h3>
                                    </div>

                                    <?php if (count($historialContratos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Código</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo</th>
                                                        <th style="padding: 10px; text-align: left;">Cargo</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Categoría
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Inicio</th>
                                                        <th style="padding: 10px; text-align: left;">Fin</th>
                                                        <th style="padding: 10px; text-align: left;">Tiempo Restante</th>
                                                        <th style="padding: 10px; text-align: left;">Duración</th>
                                                        <th style="padding: 10px; text-align: left;">Estado</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Salida</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Liquidación</th>
                                                        <th style="padding: 10px; text-align: center;">% Docs Obligatorios
                                                        </th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($historialContratos as $contrato):
                                                        // Determinar si el contrato está finalizado (por fecha_salida)
                                                        $estaFinalizado = contratoEstaFinalizado($contrato);
                                                        $estaActivo = !$estaFinalizado && contratoEstaActivo($contrato);

                                                        $estiloFila = '';
                                                        $estado = '';

                                                        if ($estaFinalizado) {
                                                            $estado = '<span style="color: #6c757d;">FINALIZADO</span>';
                                                        } elseif ($estaActivo) {
                                                            $estiloFila = 'background-color: #e8f5e9;';
                                                            $estado = '<span style="color: green; font-weight: bold;">ACTIVO</span>';
                                                        } else {
                                                            $estado = '<span style="color: #dc3545;">VENCIDO</span>';
                                                        }

                                                        // Obtener categoría del contrato desde CategoriasOperarios
                                                        $categoriaContrato = obtenerCategoriaPorContrato($contrato['CodContrato']);

                                                        // Calcular duración
                                                        $inicio = new DateTime($contrato['inicio_contrato']);

                                                        // Para la duración, usar fecha_salida si existe, sino fecha fin, sino fecha actual
                                                        if ($estaFinalizado && !empty($contrato['fecha_salida'])) {
                                                            $fin = new DateTime($contrato['fecha_salida']);
                                                        } elseif (!empty($contrato['fin_contrato']) && $contrato['fin_contrato'] != '0000-00-00') {
                                                            $fin = new DateTime($contrato['fin_contrato']);
                                                        } else {
                                                            $fin = new DateTime(); // Fecha actual para contratos activos
                                                        }

                                                        $intervalo = $inicio->diff($fin);
                                                        $duracion = $intervalo->format('%y años, %m meses, %d días');

                                                        // Calcular tiempo restante usando tu función existente
                                                        $tiempoRestante = calcularTiempoRestanteContrato(
                                                            $contrato['fin_contrato'],
                                                            $estaActivo
                                                        );
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd; <?= $estiloFila ?>">
                                                            <td style="padding: 10px;">
                                                                <?= !empty($contrato['codigo_manual_contrato']) ?
                                                                    htmlspecialchars($contrato['codigo_manual_contrato']) :
                                                                    '<span style="color: #6c757d; font-style: italic;">Sin código</span>' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($contrato['tipo_contrato'] ?? 'No especificado') ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($contrato['cargo'] ?? 'No especificado') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;">
                                                                <?= $categoriaContrato ? htmlspecialchars($categoriaContrato) : '<span style="color: #6c757d; font-style: italic;">No definida</span>' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($contrato['inicio_contrato']) ? date('d/m/Y', strtotime($contrato['inicio_contrato'])) : 'No definida' ?>
                                                            </td>
                                                            <!-- <td style="padding: 10px;"><?= !empty($contrato['fin_contrato']) ? date('d/m/Y', strtotime($contrato['fin_contrato'])) : 'No definida' ?></td> -->
                                                            <td style="padding: 10px;">
                                                                <?= !empty($contrato['fin_contrato']) && $contrato['fin_contrato'] != '0000-00-00' ?
                                                                    date('d/m/Y', strtotime($contrato['fin_contrato'])) :
                                                                    '<span style="color: #28a745; font-style: italic;">Indefinido</span>' ?>
                                                            </td>
                                                            <td style="padding: 10px;"><?= $tiempoRestante ?></td>
                                                            <td style="padding: 10px;"><?= $duracion ?></td>
                                                            <td style="padding: 10px;"><?= $estado ?></td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($contrato['fecha_salida']) && $contrato['fecha_salida'] != '0000-00-00' ?
                                                                    date('d/m/Y', strtotime($contrato['fecha_salida'])) :
                                                                    '<span style="color: #6c757d; font-style: italic;">No aplica</span>' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($contrato['fecha_liquidacion']) && $contrato['fecha_liquidacion'] != '0000-00-00' ?
                                                                    date('d/m/Y', strtotime($contrato['fecha_liquidacion'])) :
                                                                    '<span style="color: #6c757d; font-style: italic;">No definida</span>' ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <?php
                                                                $porcentajeDocs = calcularPorcentajeDocumentosObligatoriosContrato($codOperario, $contrato['CodContrato']);
                                                                $porcentaje = $porcentajeDocs['porcentaje'];
                                                                $completados = $porcentajeDocs['completados'];
                                                                $total = $porcentajeDocs['total'];

                                                                // Determinar color según porcentaje
                                                                $color = '#dc3545'; // Rojo por defecto
                                                                if ($porcentaje == 100) {
                                                                    $color = '#28a745'; // Verde
                                                                } elseif ($porcentaje >= 50) {
                                                                    $color = '#ffc107'; // Amarillo
                                                                }
                                                                ?>
                                                                <div
                                                                    style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                                                    <div
                                                                        style="width: 60px; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; position: relative;">
                                                                        <div
                                                                            style="width: <?= $porcentaje ?>%; height: 100%; background: <?= $color ?>; transition: width 0.3s;">
                                                                        </div>
                                                                    </div>
                                                                    <span
                                                                        style="font-weight: bold; color: <?= $color ?>; font-size: 0.9em;">
                                                                        <?= $porcentaje ?>%
                                                                    </span>
                                                                </div>
                                                                <small style="color: #6c757d; font-size: 0.8em;">
                                                                    (<?= $completados ?>/<?= $total ?>)
                                                                </small>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <?php if (!empty($contrato['foto'])): ?>
                                                                    <a href="<?= htmlspecialchars($contrato['foto']) ?>" target="_blank"
                                                                        class="btn-accion btn-editar" title="Ver contrato">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <?php if (!empty($contrato['foto_solicitud_renuncia'])): ?>
                                                                    <a href="<?= htmlspecialchars($contrato['foto_solicitud_renuncia']) ?>"
                                                                        target="_blank" class="btn-accion" title="Ver renuncia"
                                                                        style="color: #dc3545;">
                                                                        <i class="fas fa-file-alt"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="abrirModalLiquidacion(<?= $contrato['CodContrato'] ?>, '<?= $contrato['fecha_liquidacion'] ?? '' ?>')"
                                                                    title="Asignar/Editar Fecha de Liquidación">
                                                                    <i class="fas fa-calendar-alt"></i>
                                                                </button>

                                                                <!-- NUEVO BOTÓN -->
                                                                <button type="button" class="btn-accion"
                                                                    onclick="abrirModalEditarTerminacion(<?= $contrato['CodContrato'] ?>)"
                                                                    title="Editar Información de Terminación"
                                                                    style="color: #0E544C;">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay historial de
                                            contratos</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Sección de Archivos Adjuntos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                        <button type="button" class="btn-submit"
                                            onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar Archivo
                                        </button>
                                    </div>

                                    <?php if (count($archivosAdjuntos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Nombre</th>
                                                        <th style="padding: 10px; text-align: left;">Descripción</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Subido por</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                        <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archivosAdjuntos as $archivo):
                                                        // Formatear tamaño del archivo
                                                        $tamaño = $archivo['tamaño'];
                                                        if ($tamaño < 1024) {
                                                            $tamañoFormateado = $tamaño . ' B';
                                                        } elseif ($tamaño < 1048576) {
                                                            $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                        } else {
                                                            $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                if (!empty($archivo['tipo_documento'])) {
                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                    echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                    if ($archivo['obligatorio']) {
                                                                        echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                // Mostrar información del contrato usando codigo_manual_contrato
                                                                $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                    ?>
                                                                    <span
                                                                        style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                    <br>
                                                                    <small style="color: #6c757d;">
                                                                        <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                        <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                            - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                        <?php else: ?>
                                                                            (Activo)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                        <br>
                                                                        <small
                                                                            style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: #6c757d; font-style: italic;">
                                                                        <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                    target="_blank" class="btn-accion btn-editar"
                                                                    title="Ver archivo">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                    <input type="hidden" name="id_adjunto"
                                                                        value="<?= $archivo['id'] ?>">
                                                                    <input type="hidden" name="pestaña_adjunto"
                                                                        value="<?= $pestaña_activa ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                        class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                            adjuntos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Salario -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="salario" class="tab-pane <?= $pestaña_activa == 'salario' ? 'active' : '' ?>">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="color: #0E544C; margin: 0;">Historial de Salarios</h3>
                                    <button type="button" class="btn-submit" onclick="abrirModalSalario()"
                                        style="margin: 0;">
                                        <i class="fas fa-plus"></i> Agregar Salario Adicional
                                    </button>
                                </div>

                                <?php if (count($salarios) > 0): ?>
                                    <div style="overflow-x: auto;">
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <thead>
                                                <tr style="background-color: #0E544C; color: white;">
                                                    <th style="padding: 10px; text-align: left;">Monto</th>
                                                    <th style="padding: 10px; text-align: left;">Desde</th>
                                                    <th style="padding: 10px; text-align: left;">Hasta</th>
                                                    <th style="padding: 10px; text-align: left;">Frecuencia</th>
                                                    <th style="padding: 10px; text-align: left;">Tipo</th>
                                                    <th style="padding: 10px; text-align: left;">Observaciones</th>
                                                    <th style="padding: 10px; text-align: center;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($salarios as $salario): ?>
                                                    <tr style="border-bottom: 1px solid #ddd;">
                                                        <td style="padding: 10px;">C$ <?= number_format($salario['monto'], 2) ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= !empty($salario['inicio']) ? date('d/m/Y', strtotime($salario['inicio'])) : '' ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= !empty($salario['fin']) ? date('d/m/Y', strtotime($salario['fin'])) : 'Actual' ?>
                                                        </td>
                                                        <td style="padding: 10px;"><?= ucfirst($salario['frecuencia_pago']) ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= $salario['es_salario_inicial'] ? '<span style="color: #0E544C; font-weight: bold;">Salario Inicial</span>' : 'Salario Adicional' ?>
                                                        </td>
                                                        <td style="padding: 10px;">
                                                            <?= htmlspecialchars($salario['observaciones'] ?? '') ?>
                                                        </td>
                                                        <td style="padding: 10px; text-align: center;">
                                                            <!-- Solo permitir editar/eliminar salarios adicionales, no el inicial -->
                                                            <?php if (!$salario['es_salario_inicial']): ?>
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarSalario(<?= $salario['CodSalarioOperario'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_salario" value="eliminar">
                                                                    <input type="hidden" name="id_salario"
                                                                        value="<?= $salario['CodSalarioOperario'] ?>">
                                                                    <button style="display:none;" type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este registro de salario?')"
                                                                        class="btn-accion btn-eliminar">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span style="color: #6c757d; font-style: italic;">Editar en pestaña
                                                                    Contrato</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p style="text-align: center; color: #6c757d; padding: 20px;">No hay registros de
                                        salario</p>
                                <?php endif; ?>

                                <!-- Sección de Archivos Adjuntos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                        <button type="button" class="btn-submit"
                                            onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar Archivo
                                        </button>
                                    </div>

                                    <?php if (count($archivosAdjuntos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Nombre</th>
                                                        <th style="padding: 10px; text-align: left;">Descripción</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Subido por</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                        <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archivosAdjuntos as $archivo):
                                                        // Formatear tamaño del archivo
                                                        $tamaño = $archivo['tamaño'];
                                                        if ($tamaño < 1024) {
                                                            $tamañoFormateado = $tamaño . ' B';
                                                        } elseif ($tamaño < 1048576) {
                                                            $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                        } else {
                                                            $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                if (!empty($archivo['tipo_documento'])) {
                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                    echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                    if ($archivo['obligatorio']) {
                                                                        echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                // Mostrar información del contrato usando codigo_manual_contrato
                                                                $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                    ?>
                                                                    <span
                                                                        style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                    <br>
                                                                    <small style="color: #6c757d;">
                                                                        <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                        <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                            - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                        <?php else: ?>
                                                                            (Activo)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                        <br>
                                                                        <small
                                                                            style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: #6c757d; font-style: italic;">
                                                                        <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                    target="_blank" class="btn-accion btn-editar"
                                                                    title="Ver archivo">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                    <input type="hidden" name="id_adjunto"
                                                                        value="<?= $archivo['id'] ?>">
                                                                    <input type="hidden" name="pestaña_adjunto"
                                                                        value="<?= $pestaña_activa ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                        class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                            adjuntos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de INSS -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="inss" class="tab-pane <?= $pestaña_activa == 'inss' ? 'active' : '' ?>">
                                <!-- Sección de Documentos Obligatorios Faltantes -->
                                <div
                                    style="margin: 20px 0; padding: 15px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffeaa7;">
                                    <h4 style="color: #856404; margin-bottom: 15px;">
                                        <i class="fas fa-exclamation-triangle"></i> Documentos Obligatorios Faltantes -
                                        <?= obtenerNombrePestaña($pestaña_activa) ?>
                                    </h4>

                                    <?php
                                    $documentosFaltantesPestana = obtenerDocumentosFaltantesPestana($codOperario, $pestaña_activa);
                                    ?>

                                    <?php if (!empty($documentosFaltantesPestana)): ?>
                                        <ul style="color: #856404; margin: 0; padding-left: 20px;">
                                            <?php foreach ($documentosFaltantesPestana as $documento): ?>
                                                <li><?= htmlspecialchars($documento) ?></li>
                                            <?php endforeach; ?>
                                        </ul>

                                        <p style="color: #856404; margin: 15px 0 0 0; font-style: italic;">
                                            <i class="fas fa-info-circle"></i> Estos documentos deben ser subidos para
                                            completar la
                                            información.
                                        </p>
                                    <?php else: ?>
                                        <div style="color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;">
                                            <i class="fas fa-check-circle"></i> Todos los documentos obligatorios están
                                            completos para
                                            esta pestaña.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php
                                // Obtener datos del contrato actual con información INSS
                                $contratoConINSS = obtenerContratoConINSS($codOperario);
                                ?>

                                <?php if (isset($_GET['confirmar']) && $_GET['confirmar'] == 1 && isset($_SESSION['confirmacion_inss'])): ?>
                                    <div class="alert alert-warning">
                                        <h4>Confirmación requerida</h4>
                                        <p>Ya existe un salario INSS registrado para este colaborador. ¿Desea registrar un
                                            nuevo salario
                                            INSS?
                                            El registro anterior será finalizado automáticamente.</p>
                                        <form method="POST" action="">
                                            <input type="hidden" name="pestaña" value="inss">

                                            <div class="form-row">
                                                <div class="form-col">
                                                    <div class="form-group">
                                                        <label for="codigo_inss">Número de Seguro INSS</label>
                                                        <input type="text" id="codigo_inss" name="codigo_inss"
                                                            class="form-control"
                                                            value="<?= htmlspecialchars($colaborador['codigo_inss'] ?? '') ?>">
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="hospital_riesgo_laboral">Hospital Asignado para Riesgo
                                                            Laboral</label>
                                                        <input type="text" id="hospital_riesgo_laboral"
                                                            name="hospital_riesgo_laboral" class="form-control"
                                                            value="<?= htmlspecialchars($colaborador['hospital_riesgo_laboral'] ?? '') ?>">
                                                    </div>
                                                </div>

                                                <div class="form-col">
                                                    <div class="form-group">
                                                        <label for="numero_planilla">Número de Planilla</label>
                                                        <select id="numero_planilla" name="numero_planilla"
                                                            class="form-control">
                                                            <option value="">Seleccionar planilla...</option>
                                                            <?php foreach ($planillasPatronales as $planilla): ?>
                                                                <option value="<?= $planilla['CodPlanilla'] ?>" <?= ($contratoConINSS && $contratoConINSS['numero_planilla'] == $planilla['CodPlanilla']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($planilla['nombre_planilla']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="hospital_inss">Hospital Asociado</label>
                                                        <input type="text" id="hospital_inss" name="hospital_inss"
                                                            class="form-control"
                                                            value="<?= $contratoConINSS ? htmlspecialchars($contratoConINSS['hospital_inss'] ?? '') : '' ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn-submit">Guardar Cambios INSS</button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="pestaña" value="inss">

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="codigo_inss">Número de Seguro INSS</label>
                                                <input type="text" id="codigo_inss" name="codigo_inss" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['codigo_inss'] ?? '') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="hospital_riesgo_laboral">Hospital Asignado para Riesgo
                                                    Laboral</label>
                                                <input type="text" id="hospital_riesgo_laboral"
                                                    name="hospital_riesgo_laboral" class="form-control"
                                                    value="<?= htmlspecialchars($colaborador['hospital_riesgo_laboral'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="numero_planilla">Número de Planilla</label>
                                                <select id="numero_planilla" name="numero_planilla" class="form-control">
                                                    <option value="">Seleccionar planilla...</option>
                                                    <?php foreach ($planillasPatronales as $planilla): ?>
                                                        <option value="<?= $planilla['CodPlanilla'] ?>" <?= ($contratoConINSS && $contratoConINSS['numero_planilla'] == $planilla['CodPlanilla']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($planilla['nombre_planilla']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="hospital_inss">Hospital Asociado</label>
                                                <input type="text" id="hospital_inss" name="hospital_inss"
                                                    class="form-control"
                                                    value="<?= $contratoConINSS ? htmlspecialchars($contratoConINSS['hospital_inss'] ?? '') : '' ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-submit">Guardar Cambios INSS</button>
                                </form>

                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0 20px 0; display:none;">
                                    <h3 style="color: #0E544C; margin: 0;">Historial de Salarios INSS</h3>
                                    <button type="button" class="btn-submit" onclick="abrirModalSalarioINSS()"
                                        style="margin: 0;">
                                        <i class="fas fa-plus"></i> Agregar Salario INSS
                                    </button>
                                </div>

                                <div style="display:none;">
                                    <?php if (count($salariosINSS) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #0E544C; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Salario INSS</th>
                                                        <th style="padding: 10px; text-align: left;">Inicio</th>
                                                        <th style="padding: 10px; text-align: left;">Final</th>
                                                        <th style="padding: 10px; text-align: left;">Observaciones</th>
                                                        <th style="padding: 10px; text-align: left;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($salariosINSS as $salario): ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">C$
                                                                <?= number_format($salario['monto_salario_inss'], 2) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($salario['inicio']) ? date('d/m/Y', strtotime($salario['inicio'])) : '' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($salario['final']) ? date('d/m/Y', strtotime($salario['final'])) : 'Actual' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($salario['observaciones_inss'] ?? '') ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarSalarioINSS(<?= $salario['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay registros de
                                            salario INSS
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Sección de Archivos Adjuntos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                        <button type="button" class="btn-submit"
                                            onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar Archivo
                                        </button>
                                    </div>

                                    <?php if (count($archivosAdjuntos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Nombre</th>
                                                        <th style="padding: 10px; text-align: left;">Descripción</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Subido por</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                        <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archivosAdjuntos as $archivo):
                                                        $tamaño = $archivo['tamaño'];
                                                        if ($tamaño < 1024) {
                                                            $tamañoFormateado = $tamaño . ' B';
                                                        } elseif ($tamaño < 1048576) {
                                                            $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                        } else {
                                                            $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                if (!empty($archivo['tipo_documento'])) {
                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                    echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                    if ($archivo['obligatorio']) {
                                                                        echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                // Mostrar información del contrato usando codigo_manual_contrato
                                                                $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                    ?>
                                                                    <span
                                                                        style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                    <br>
                                                                    <small style="color: #6c757d;">
                                                                        <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                        <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                            - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                        <?php else: ?>
                                                                            (Activo)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                        <br>
                                                                        <small
                                                                            style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: #6c757d; font-style: italic;">
                                                                        <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                    target="_blank" class="btn-accion btn-editar"
                                                                    title="Ver archivo">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                    <input type="hidden" name="id_adjunto"
                                                                        value="<?= $archivo['id'] ?>">
                                                                    <input type="hidden" name="pestaña_adjunto"
                                                                        value="<?= $pestaña_activa ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                        class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                            adjuntos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Movimientos -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="movimientos" class="tab-pane <?= $pestaña_activa == 'movimientos' ? 'active' : '' ?>">
                                <?php
                                $historialCargos = obtenerHistorialCargos($codOperario);
                                $cargosDisponibles = obtenerTodosCargos();
                                $sucursales = obtenerTodasSucursales();
                                ?>

                                <div style="margin-bottom: 30px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Agregar Nuevo Cargo</h3>

                                    <?php if ($contratoActual): ?>
                                        <div class="readonly-info" style="margin-bottom: 20px;">
                                            <p><strong>Contrato Asociado:</strong>
                                                <?= htmlspecialchars($contratoActual['codigo_manual_contrato'] ?? 'Sin código') ?>
                                            </p>
                                            <p><strong>Los movimientos se asociarán automáticamente a este contrato</strong>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" action="">
                                        <input type="hidden" name="pestaña" value="movimientos">
                                        <input type="hidden" name="accion_movimiento" value="agregar">

                                        <div class="form-row">
                                            <div class="form-col">
                                                <div class="form-group">
                                                    <label for="nuevo_cod_cargo">Cargo *</label>
                                                    <select id="nuevo_cod_cargo" name="cod_cargo" class="form-control"
                                                        required>
                                                        <option value="">Seleccionar cargo...</option>
                                                        <?php foreach ($cargosDisponibles as $cargo): ?>
                                                            <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                                                <?= htmlspecialchars($cargo['Nombre']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="form-group">
                                                    <label for="nuevo_sucursal">Sucursal *</label>
                                                    <select id="nuevo_sucursal" name="sucursal" class="form-control"
                                                        required>
                                                        <option value="">Seleccionar sucursal...</option>
                                                        <?php foreach ($sucursales as $sucursal): ?>
                                                            <option value="<?= $sucursal['codigo'] ?>">
                                                                <?= htmlspecialchars($sucursal['nombre']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="form-col">
                                                <div class="form-group">
                                                    <label for="nuevo_fecha_inicio">Fecha de Inicio *</label>
                                                    <input type="date" id="nuevo_fecha_inicio" name="fecha_inicio"
                                                        class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                </div>

                                                <!-- Tipo de contrato oculto con valor 3 -->
                                                <input type="hidden" name="tipo_contrato" value="3">
                                            </div>
                                        </div>

                                        <button type="submit" class="btn-submit">Agregar Cargo</button>
                                    </form>
                                </div>

                                <div style="border-top: 2px solid #0E544C; padding-top: 20px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Historial de Cargos</h3>

                                    <?php if (count($historialCargos) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #0E544C; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Cargo</th>
                                                        <th style="padding: 10px; text-align: left;">Sucursal</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Inicio</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Fin</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo Contrato</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($historialCargos as $cargo): ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cargo['nombre_cargo'] ?? 'No definido') ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cargo['nombre_sucursal'] ?? 'No definida') ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($cargo['Fecha']) ? date('d/m/Y', strtotime($cargo['Fecha'])) : 'No definida' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($cargo['Fin']) ? date('d/m/Y', strtotime($cargo['Fin'])) : 'Activo' ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($cargo['nombre_tipo_contrato'] ?? 'No definido') ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarMovimiento(<?= $cargo['CodAsignacionNivelesCargos'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay historial de
                                            cargos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Categoría -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="categoria" class="tab-pane <?= $pestaña_activa == 'categoria' ? 'active' : '' ?>">
                                <form method="POST" action="">
                                    <input type="hidden" name="accion_categoria" value="agregar">
                                    <input type="hidden" name="pestaña" value="categoria">

                                    <div class="form-row">
                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="id_categoria">Categoría *</label>
                                                <select id="id_categoria" name="id_categoria" class="form-control" required>
                                                    <option value="">Seleccionar categoría...</option>
                                                    <?php foreach ($todasCategorias as $categoria): ?>
                                                        <option value="<?= $categoria['idCategoria'] ?>">
                                                            <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                            (Peso: <?= $categoria['Peso'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-col">
                                            <div class="form-group">
                                                <label for="fecha_inicio">Fecha de Inicio *</label>
                                                <input type="date" id="fecha_inicio" name="fecha_inicio"
                                                    class="form-control" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-submit">Agregar Categoría</button>
                                </form>

                                <div style="margin-top: 40px; border-top: 2px solid #0E544C; padding-top: 20px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Historial de Categorías</h3>

                                    <?php if (count($categoriasColaborador) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #0E544C; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Categoría</th>
                                                        <th style="padding: 10px; text-align: left;">Peso</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Inicio</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha Fin</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Estado
                                                        </th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($categoriasColaborador as $categoria):
                                                        $estado = empty($categoria['FechaFin']) ?
                                                            '<span style="color: green; font-weight: bold;">ACTIVA</span>' :
                                                            '<span style="color: #6c757d;">INACTIVA</span>';
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                            </td>
                                                            <td style="padding: 10px;"><?= $categoria['Peso'] ?></td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y', strtotime($categoria['FechaInicio'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= !empty($categoria['FechaFin']) ? date('d/m/Y', strtotime($categoria['FechaFin'])) : 'No definida' ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $estado ?></td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <button type="button" class="btn-accion btn-editar"
                                                                    onclick="editarCategoria(<?= $categoria['id'] ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay categorías
                                            registradas</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Sección de Archivos Adjuntos -->
                                <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                        <button type="button" class="btn-submit"
                                            onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                            <i class="fas fa-plus"></i> Agregar Archivo
                                        </button>
                                    </div>

                                    <?php
                                    // Obtener archivos adjuntos de la pestaña categoría
                                    $archivosAdjuntosCategoria = obtenerArchivosAdjuntos($codOperario, 'categoria');
                                    ?>

                                    <?php if (count($archivosAdjuntosCategoria) > 0): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background-color: #6c757d; color: white;">
                                                        <th style="padding: 10px; text-align: left;">Nombre</th>
                                                        <th style="padding: 10px; text-align: left;">Descripción</th>
                                                        <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                        </th>
                                                        <th style="padding: 10px; text-align: left;">Subido por</th>
                                                        <th style="padding: 10px; text-align: left;">Fecha</th>
                                                        <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                        <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                        <th style="padding: 10px; text-align: center;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($archivosAdjuntosCategoria as $archivo):
                                                        $tamaño = $archivo['tamaño'];
                                                        if ($tamaño < 1024) {
                                                            $tamañoFormateado = $tamaño . ' B';
                                                        } elseif ($tamaño < 1048576) {
                                                            $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                        } else {
                                                            $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                        }
                                                        ?>
                                                        <tr style="border-bottom: 1px solid #ddd;">
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                            </td>
                                                            <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                if (!empty($archivo['tipo_documento'])) {
                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                    echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                    if ($archivo['obligatorio']) {
                                                                        echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                    }
                                                                } else {
                                                                    echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td style="padding: 10px;">
                                                                <?php
                                                                // Mostrar información del contrato usando codigo_manual_contrato
                                                                $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                    ?>
                                                                    <span
                                                                        style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                    <br>
                                                                    <small style="color: #6c757d;">
                                                                        <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                        <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                            - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                        <?php else: ?>
                                                                            (Activo)
                                                                        <?php endif; ?>
                                                                    </small>
                                                                    <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                        <br>
                                                                        <small
                                                                            style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <span style="color: #6c757d; font-style: italic;">
                                                                        <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="padding: 10px; text-align: center;">
                                                                <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                    target="_blank" class="btn-accion btn-editar"
                                                                    title="Ver archivo">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                    <input type="hidden" name="id_adjunto"
                                                                        value="<?= $archivo['id'] ?>">
                                                                    <input type="hidden" name="pestaña_adjunto"
                                                                        value="<?= $pestaña_activa ?>">
                                                                    <button type="submit"
                                                                        onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                        class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                            adjuntos</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Adendums -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="adendums" class="tab-pane <?= $pestaña_activa == 'adendums' ? 'active' : '' ?>">
                                <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                                    <?php if (!tieneContratoActivo($codOperario)): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            No se puede agregar información de <?= $pestaña_activa ?> porque el colaborador no
                                            tiene un
                                            contrato activo.
                                            Por favor, complete la información del contrato primero.
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-bottom: 30px;">
                                            <h3 style="color: #0E544C; margin-bottom: 15px; display:none;">Nuevo
                                                Adendum/Movimiento</h3>

                                            <form method="POST" action="">
                                                <input type="hidden" name="accion_adendum" value="agregar">
                                                <input type="hidden" name="pestaña" value="adendums">

                                                <div class="form-row">
                                                    <div class="form-col">
                                                        <div class="form-group">
                                                            <label for="tipo_adendum">Tipo de Adendum *</label>
                                                            <select id="tipo_adendum" name="tipo_adendum" class="form-control"
                                                                required onchange="actualizarCamposAdendum()">
                                                                <option value="">Seleccionar tipo...</option>
                                                                <option value="cargo">Cambio de Cargo</option>
                                                                <option value="salario">Ajuste Salarial</option>
                                                                <option value="ambos">Cambio de Cargo y Salario</option>
                                                            </select>
                                                        </div>

                                                        <div class="form-group" id="grupo_cargo">
                                                            <label for="cod_cargo_adendum">Cargo *</label>
                                                            <select id="cod_cargo_adendum" name="cod_cargo" class="form-control">
                                                                <option value="">Seleccionar cargo...</option>
                                                                <?php foreach ($cargosDisponibles as $cargo): ?>
                                                                    <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                                                        <?= htmlspecialchars($cargo['Nombre']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="form-group" id="grupo_sucursal">
                                                            <label for="sucursal_adendum">Sucursal *</label>
                                                            <select id="sucursal_adendum" name="sucursal" class="form-control">
                                                                <option value="">Seleccionar sucursal...</option>
                                                                <?php foreach ($sucursales as $sucursal): ?>
                                                                    <option value="<?= $sucursal['codigo'] ?>">
                                                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="form-col">
                                                        <div class="form-group" id="grupo_categoria" style="display:none;">
                                                            <div class="form-group" id="grupo_categoria" style="display:none;">
                                                                <label for="id_categoria_adendum">Categoría *</label>
                                                                <select id="id_categoria_adendum" name="id_categoria"
                                                                    class="form-control">
                                                                    <option value="">Seleccionar categoría...</option>
                                                                    <?php foreach ($todasCategorias as $categoria): ?>
                                                                        <option value="<?= $categoria['idCategoria'] ?>">
                                                                            <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                                            (Peso: <?= $categoria['Peso'] ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="form-group" id="grupo_salario">
                                                            <label for="salario_adendum">Salario (C$) *</label>
                                                            <input type="number" id="salario_adendum" name="salario"
                                                                class="form-control" step="0.01" min="0" placeholder="0.00">
                                                            <small style="color: #6c757d;">Salario de referencia:
                                                                <?php
                                                                $salarioReferencia = obtenerSalarioReferencia($codOperario);
                                                                echo 'C$ ' . number_format($salarioReferencia, 2);
                                                                ?>
                                                            </small>
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="fecha_inicio_adendum">Fecha de Inicio *</label>
                                                            <input type="date" id="fecha_inicio_adendum" name="fecha_inicio"
                                                                class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                        </div>

                                                        <div class="form-group" style="display:none;">
                                                            <label for="fecha_fin_adendum">Fecha de Fin (opcional)</label>
                                                            <input type="date" id="fecha_fin_adendum" name="fecha_fin"
                                                                class="form-control">
                                                            <small style="color: #6c757d;">
                                                                Dejar vacío si es un adendum indefinido. Solo se aplica si es el
                                                                primer
                                                                adendum o si desea especificar una fecha final.
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label for="observaciones_adendum">Observaciones</label>
                                                    <textarea id="observaciones_adendum" name="observaciones" class="form-control"
                                                        rows="3" placeholder="Observaciones sobre el adendum..."></textarea>
                                                </div>

                                                <button type="submit" class="btn-submit">
                                                    Agregar Adendum
                                                </button>
                                            </form>
                                        </div>

                                        <div style="border-top: 2px solid #0E544C; padding-top: 20px;">
                                            <h3 style="color: #0E544C; margin-bottom: 15px;">Historial de Adendums</h3>

                                            <?php if (count($adendumsColaborador) > 0): ?>
                                                <div style="overflow-x: auto;">
                                                    <table style="width: 100%; border-collapse: collapse;">
                                                        <thead>
                                                            <tr style="background-color: #0E544C; color: white;">
                                                                <th style="padding: 10px; text-align: left;">Tipo</th>
                                                                <th style="padding: 10px; text-align: left;">Cargo</th>
                                                                <th style="padding: 10px; text-align: left; display:none;">Categoría
                                                                </th>
                                                                <th style="padding: 10px; text-align: left;">Salario</th>
                                                                <th style="padding: 10px; text-align: left;">Sucursal</th>
                                                                <th style="padding: 10px; text-align: left;">Fecha Inicio</th>
                                                                <th style="padding: 10px; text-align: left;">Fecha Fin</th>
                                                                <th style="padding: 10px; text-align: left;">Estado</th>
                                                                <th style="padding: 10px; text-align: center;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($adendumsColaborador as $adendum):
                                                                $estado = empty($adendum['Fin']) ?
                                                                    '<span style="color: green; font-weight: bold;">ACTIVO</span>' :
                                                                    '<span style="color: #6c757d;">INACTIVO</span>';

                                                                $tipoTexto = [
                                                                    'cargo' => 'Cambio Cargo',
                                                                    'salario' => 'Ajuste Salarial',
                                                                    'ambos' => 'Cargo y Salario'
                                                                ];
                                                                ?>
                                                                <tr style="border-bottom: 1px solid #ddd;">
                                                                    <td style="padding: 10px;">
                                                                        <?= $tipoTexto[$adendum['TipoAdendum']] ?? 'No definido' ?>
                                                                    </td>
                                                                    <td style="padding: 10px;">
                                                                        <?= htmlspecialchars($adendum['nombre_cargo'] ?? 'No definido') ?>
                                                                    </td>

                                                                    <td style="padding: 10px;">
                                                                        <?= $adendum['Salario'] ? 'C$ ' . number_format($adendum['Salario'], 2) : 'No definido' ?>
                                                                    </td>
                                                                    <td style="padding: 10px;">
                                                                        <?= htmlspecialchars($adendum['nombre_sucursal'] ?? 'No definida') ?>
                                                                    </td>
                                                                    <td style="padding: 10px;">
                                                                        <?= date('d/m/Y', strtotime($adendum['Fecha'])) ?>
                                                                    </td>
                                                                    <td style="padding: 10px;">
                                                                        <?= !empty($adendum['Fin']) ? date('d/m/Y', strtotime($adendum['Fin'])) : 'No definida' ?>
                                                                    </td>
                                                                    <td style="padding: 10px;"><?= $estado ?></td>
                                                                    <td style="padding: 10px; text-align: center;">
                                                                        <button style="display:none;" type="button"
                                                                            class="btn-accion btn-editar"
                                                                            onclick="editarAdendum(<?= $adendum['CodAsignacionNivelesCargos'] ?>)">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                        <?php if (empty($adendum['Fin'])): ?>
                                                                            <button type="button" class="btn-accion"
                                                                                onclick="abrirModalFinalizarAdenda(<?= $adendum['CodAsignacionNivelesCargos'] ?>)"
                                                                                style="color: #dc3545; display:none;" title="Finalizar Adenda">
                                                                                <i class="fas fa-flag-checkered"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div
                                                    style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
                                                    <i class="fas fa-folder-open"
                                                        style="font-size: 3rem; color: #6c757d; margin-bottom: 15px;"></i>
                                                    <h4 style="color: #6c757d;">No hay adendums registrados</h4>
                                                    <p style="color: #6c757d;">Para subir archivos en esta pestaña, primero debe
                                                        crear un
                                                        adendum.</p>

                                                    <div style="margin-top: 20px;">
                                                        <p style="color: #0E544C; font-weight: bold;">
                                                            <i class="fas fa-info-circle"></i> Flujo correcto:
                                                        </p>
                                                        <ol style="text-align: left; display: inline-block; color: #6c757d;">
                                                            <li>Crear el adendum usando el formulario superior</li>
                                                            <li>Luego podrá subir archivos asociados al adendum</li>
                                                        </ol>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Sección de Archivos Adjuntos -->
                                        <div style="margin-top: 40px; border-top: 2px solid #6c757d; padding-top: 20px;">
                                            <div
                                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                                <h3 style="color: #6c757d; margin: 0;">Archivos Adjuntos</h3>
                                                <button type="button" class="btn-submit"
                                                    onclick="abrirModalAdjunto('<?= $pestaña_activa ?>')" style="margin: 0;">
                                                    <i class="fas fa-plus"></i> Agregar Archivo
                                                </button>
                                            </div>

                                            <?php
                                            $archivosAdjuntosAdendums = obtenerArchivosAdjuntos($codOperario, 'adendums');
                                            ?>

                                            <?php if (count($archivosAdjuntosAdendums) > 0): ?>
                                                <div style="overflow-x: auto;">
                                                    <?php
                                                    // Agrupar archivos por adendum
                                                    $archivosPorAdendum = [];
                                                    foreach ($archivosAdjuntosAdendums as $archivo) {
                                                        $adendumId = $archivo['cod_adendum_asociado'] ?? 'sin_adendum';
                                                        if (!isset($archivosPorAdendum[$adendumId])) {
                                                            $archivosPorAdendum[$adendumId] = [
                                                                'info' => $archivo, // Información del adendum
                                                                'archivos' => []
                                                            ];
                                                        }
                                                        $archivosPorAdendum[$adendumId]['archivos'][] = $archivo;
                                                    }

                                                    // Ordenar por ID de adendum (más reciente primero)
                                                    krsort($archivosPorAdendum);
                                                    ?>

                                                    <?php foreach ($archivosPorAdendum as $adendumId => $grupo): ?>
                                                        <?php if ($adendumId !== 'sin_adendum'): ?>
                                                            <div
                                                                style="background: #f8f9fa; padding: 10px; margin: 15px 0; border-left: 4px solid #0E544C;">
                                                                <strong>Adendum: </strong>
                                                                <?= htmlspecialchars($grupo['info']['TipoAdendum'] ?? 'N/A') ?> |
                                                                <strong>Cargo:
                                                                </strong><?= htmlspecialchars($grupo['info']['nombre_cargo_adendum'] ?? 'N/A') ?>
                                                                |
                                                                <strong>Salario: </strong>C$
                                                                <?= number_format($grupo['info']['salario_adendum'] ?? 0, 2) ?> |
                                                                <strong>Fecha:
                                                                </strong><?= !empty($grupo['info']['FechaInicio']) ? date('d/m/Y', strtotime($grupo['info']['FechaInicio'])) : 'N/A' ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div
                                                                style="background: #fff3cd; padding: 10px; margin: 15px 0; border-left: 4px solid #ffc107;">
                                                                <strong>Archivos sin adendum asociado</strong>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Tabla de archivos para este adendum -->
                                                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                                                            <thead>
                                                                <tr style="background-color: #6c757d; color: white;">
                                                                    <th style="padding: 10px; text-align: left;">Nombre</th>
                                                                    <th style="padding: 10px; text-align: left;">Descripción</th>
                                                                    <th style="padding: 10px; text-align: left; display:none;">Tamaño
                                                                    </th>
                                                                    <th style="padding: 10px; text-align: left;">Subido por</th>
                                                                    <th style="padding: 10px; text-align: left;">Fecha</th>
                                                                    <th style="padding: 10px; text-align: left;">Tipo de Documento</th>
                                                                    <th style="padding: 10px; text-align: left;">Contrato Asociado</th>
                                                                    <th style="padding: 10px; text-align: center;"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($archivosAdjuntosAdendums as $archivo):
                                                                    $tamaño = $archivo['tamaño'];
                                                                    if ($tamaño < 1024) {
                                                                        $tamañoFormateado = $tamaño . ' B';
                                                                    } elseif ($tamaño < 1048576) {
                                                                        $tamañoFormateado = round($tamaño / 1024, 2) . ' KB';
                                                                    } else {
                                                                        $tamañoFormateado = round($tamaño / 1048576, 2) . ' MB';
                                                                    }
                                                                    ?>
                                                                    <tr style="border-bottom: 1px solid #ddd;">
                                                                        <td style="padding: 10px;">
                                                                            <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                                        </td>
                                                                        <td style="padding: 10px;">
                                                                            <?= htmlspecialchars($archivo['descripcion'] ?? '-') ?>
                                                                        </td>
                                                                        <td style="padding: 10px; display:none;"><?= $tamañoFormateado ?>
                                                                        </td>
                                                                        <td style="padding: 10px;">
                                                                            <?= htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                                        </td>
                                                                        <td style="padding: 10px;">
                                                                            <?= date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                                        </td>
                                                                        <td style="padding: 10px;">
                                                                            <?php
                                                                            if (!empty($archivo['tipo_documento'])) {
                                                                                $tiposDocumentos = obtenerTiposDocumentosPorPestaña($pestaña_activa);
                                                                                $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                                echo htmlspecialchars($todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento']);

                                                                                if ($archivo['obligatorio']) {
                                                                                    echo ' <span style="color: #dc3545;" title="Documento Obligatorio">●</span>';
                                                                                }
                                                                            } else {
                                                                                echo '<span style="color: #6c757d; font-style: italic;">Sin categorizar</span>';
                                                                            }
                                                                            ?>
                                                                        </td>
                                                                        <td style="padding: 10px;">
                                                                            <?php
                                                                            // Mostrar información del contrato usando codigo_manual_contrato
                                                                            $pestañasConContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                                                                            if (in_array($pestaña_activa, $pestañasConContrato) && !empty($archivo['codigo_manual_contrato'])):
                                                                                ?>
                                                                                <span
                                                                                    style="font-weight: 500;"><?= htmlspecialchars($archivo['codigo_manual_contrato']) ?></span>
                                                                                <br>
                                                                                <small style="color: #6c757d;">
                                                                                    <?= !empty($archivo['inicio_contrato']) ? date('d/m/Y', strtotime($archivo['inicio_contrato'])) : 'Fecha no disponible' ?>
                                                                                    <?php if (!empty($archivo['fin_contrato']) && $archivo['fin_contrato'] != '0000-00-00'): ?>
                                                                                        - <?= date('d/m/Y', strtotime($archivo['fin_contrato'])) ?>
                                                                                    <?php else: ?>
                                                                                        (Activo)
                                                                                    <?php endif; ?>
                                                                                </small>
                                                                                <?php if (!empty($archivo['tipo_contrato'])): ?>
                                                                                    <br>
                                                                                    <small
                                                                                        style="color: #0E544C;"><?= htmlspecialchars($archivo['tipo_contrato']) ?></small>
                                                                                <?php endif; ?>
                                                                            <?php else: ?>
                                                                                <span style="color: #6c757d; font-style: italic;">
                                                                                    <?= in_array($pestaña_activa, $pestañasConContrato) ? 'Sin contrato asociado' : 'No aplica' ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td style="padding: 10px; text-align: center;">
                                                                            <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                                target="_blank" class="btn-accion btn-editar"
                                                                                title="Ver archivo">
                                                                                <i class="fas fa-eye"></i>
                                                                            </a>
                                                                            <form method="POST" action="" style="display: inline;">
                                                                                <input type="hidden" name="accion_adjunto" value="eliminar">
                                                                                <input type="hidden" name="id_adjunto"
                                                                                    value="<?= $archivo['id'] ?>">
                                                                                <input type="hidden" name="pestaña_adjunto"
                                                                                    value="<?= $pestaña_activa ?>">
                                                                                <button type="submit"
                                                                                    onclick="return confirm('¿Está seguro de eliminar este archivo?')"
                                                                                    class="btn-accion btn-eliminar" title="Eliminar archivo">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
                                                                            </form>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p style="text-align: center; color: #6c757d; padding: 20px;">No hay archivos
                                                    adjuntos</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Expediente Digital -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="expediente-digital"
                                class="tab-pane <?= $pestaña_activa == 'expediente-digital' ? 'active' : '' ?>">
                                <?php
                                $expedienteCompleto = obtenerExpedienteDigitalCompleto($codOperario);
                                $documentosFaltantes = obtenerDocumentosFaltantes($codOperario);
                                $totalArchivos = array_sum(array_map('count', $expedienteCompleto));
                                ?>

                                <!-- Resumen del Expediente -->
                                <div class="resumen-expediente"
                                    style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; display:none;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Resumen del Expediente Digital</h3>

                                    <div
                                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                        <div
                                            style="text-align: center; padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            <div style="font-size: 2rem; font-weight: bold; color: #0E544C;">
                                                <?= $totalArchivos ?>
                                            </div>
                                            <div style="color: #6c757d;">Total de Documentos</div>
                                        </div>

                                        <?php foreach ($expedienteCompleto as $categoria => $archivos): ?>
                                            <div
                                                style="text-align: center; padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <div style="font-size: 2rem; font-weight: bold; color: #0E544C;">
                                                    <?= count($archivos) ?>
                                                </div>
                                                <div style="color: #6c757d;"><?= $categoria ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Estado de finalización -->
                                    <?php $estadoGlobal = verificarEstadoGlobalDocumentos($codOperario); ?>
                                    <div style="text-align: center; padding: 15px; display:none; background: <?=
                                        $estadoGlobal == 'completo' ? '#d4edda' :
                                        ($estadoGlobal == 'parcial' ? '#fff3cd' : '#f8d7da')
                                        ?>; border-radius: 6px; border: 1px solid <?=
                                        $estadoGlobal == 'completo' ? '#c3e6cb' :
                                        ($estadoGlobal == 'parcial' ? '#ffeaa7' : '#f5c6cb')
                                        ?>;">
                                        <h4 style="margin: 0; color: <?=
                                            $estadoGlobal == 'completo' ? '#155724' :
                                            ($estadoGlobal == 'parcial' ? '#856404' : '#721c24')
                                            ?>;">
                                            <?=
                                                $estadoGlobal == 'completo' ? '✅ Expediente Completo' :
                                                ($estadoGlobal == 'parcial' ? '⏳ Expediente Parcial' : '❌ Expediente Incompleto')
                                                ?>
                                        </h4>
                                        <p style="margin: 5px 0 0 0; color: <?=
                                            $estadoGlobal == 'completo' ? '#155724' :
                                            ($estadoGlobal == 'parcial' ? '#856404' : '#721c24')
                                            ?>;">
                                            <?=
                                                $estadoGlobal == 'completo' ? 'Todos los documentos obligatorios están subidos' :
                                                ($estadoGlobal == 'parcial' ? 'Faltan algunos documentos obligatorios' : 'Faltan la mayoría de documentos obligatorios')
                                                ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Documentos Faltantes -->
                                <?php if (!empty($documentosFaltantes)): ?>
                                    <div class="documentos-faltantes"
                                        style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffeaa7; display:none;">
                                        <h4 style="color: #856404; margin-bottom: 15px;">
                                            <i class="fas fa-exclamation-triangle"></i> Documentos Obligatorios Faltantes
                                        </h4>

                                        <?php foreach ($documentosFaltantes as $pestaña => $info): ?>
                                            <div style="margin-bottom: 15px;">
                                                <h5 style="color: #856404; margin-bottom: 10px;">
                                                    <?= $info['pestaña_nombre'] ?>
                                                </h5>
                                                <ul style="color: #856404; margin: 0; padding-left: 20px;">
                                                    <?php foreach ($info['faltantes'] as $documento): ?>
                                                        <li><?= htmlspecialchars($documento['nombre']) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>

                                        <p style="color: #856404; margin: 15px 0 0 0; font-style: italic;">
                                            <i class="fas fa-info-circle"></i> Estos documentos deben ser subidos en sus
                                            pestañas
                                            correspondientes.
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="sin-faltantes"
                                        style="background: #d4edda; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #c3e6cb;">
                                        <h4 style="color: #155724; margin: 0;">
                                            <i class="fas fa-check-circle"></i> Todos los documentos obligatorios están
                                            completos
                                        </h4>
                                    </div>
                                <?php endif; ?>

                                <!-- Expediente Digital Organizado -->
                                <div class="expediente-organizado">
                                    <h3 style="color: #0E544C; margin-bottom: 20px; display:none;">Expediente Digital
                                        Organizado
                                    </h3>

                                    <!-- Leyenda -->
                                    <div
                                        style="background: #e9ecef; padding: 15px; border-radius: 5px; margin-top: 10px; margin-bottom:10px;">
                                        <h5 style="margin: 0 0 10px 0; color: #495057;">Leyenda:</h5>
                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span
                                                    style="display: inline-block; width: 12px; height: 12px; background: #dc3545; border-radius: 50%;"></span>
                                                <span style="font-size: 0.9em;">Documento Obligatorio</span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <span
                                                    style="display: inline-block; width: 12px; height: 12px; background: #6c757d; border-radius: 50%;"></span>
                                                <span style="font-size: 0.9em;">Documento Informativo</span>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                <i class="fas fa-eye" style="color: #0E544C;"></i>
                                                <span style="font-size: 0.9em;">Solo visualización</span>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                    // Obtener todos los documentos esperados (incluyendo faltantes)
                                    $expedienteCompletoConFaltantes = obtenerExpedienteCompletoConFaltantes($codOperario);
                                    ?>

                                    <?php if (!empty($expedienteCompletoConFaltantes)): ?>
                                        <div class="contenido-categoria"
                                            style="border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; padding: 0;">
                                            <table style="width: 100%; border-collapse: collapse;">
                                                <thead>
                                                    <tr style="background: #f8f9fa;">
                                                        <th style="padding: 12px; text-align: left; width: 15%;">Estado</th>
                                                        <th style="padding: 12px; text-align: left; width: 25%;">Documento
                                                        </th>
                                                        <th style="padding: 12px; text-align: left; width: 25%;">Descripción
                                                        </th>
                                                        <th style="padding: 12px; text-align: left; width: 15%;">Pestaña
                                                            Origen</th>
                                                        <th style="padding: 12px; text-align: left; width: 20%;">Subido por
                                                        </th>
                                                        <th style="padding: 12px; text-align: left; width: 15%;">Fecha</th>
                                                        <th style="padding: 12px; text-align: center; width: 10%;"></th>
                                                    </tr>
                                                </thead>
                                            </table>
                                        </div>

                                        <?php foreach ($expedienteCompletoConFaltantes as $categoriaPrincipal => $subcategorias): ?>
                                            <div class="categoria-expediente" style="margin-bottom: 30px;">
                                                <div class="header-categoria"
                                                    style="background: #0E544C; color: white; padding: 12px 15px; border-radius: 5px 5px 0 0;">
                                                    <h4
                                                        style="margin: 0; display: flex; justify-content: space-between; align-items: center;">
                                                        <span>
                                                            <?= htmlspecialchars($categoriaPrincipal) ?>
                                                            <small>(<?= array_sum(array_map('count', $subcategorias)) ?>
                                                                documento<?= array_sum(array_map('count', $subcategorias)) !== 1 ? 's' : '' ?>)</small>
                                                        </span>
                                                        <i class="fas fa-folder"></i>
                                                    </h4>
                                                </div>

                                                <div class="contenido-categoria"
                                                    style="border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; padding: 0;">
                                                    <?php foreach ($subcategorias as $subcategoria => $archivos): ?>
                                                        <!-- Tabla de documentos -->
                                                        <table style="width: 100%; border-collapse: collapse;">
                                                            <tbody>
                                                                <?php foreach ($archivos as $archivo): ?>
                                                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                                                        <td style="padding: 12px;">
                                                                            <?php if ($archivo['tipo'] === 'faltante'): ?>
                                                                                <span
                                                                                    style="display: inline-block; padding: 3px 8px; background: #dc3545; color: white; border-radius: 12px; font-size: 0.8em;">
                                                                                    FALTANTE
                                                                                </span>
                                                                            <?php else: ?>
                                                                                <?php if (!empty($archivo['tipo_documento'])): ?>
                                                                                    <?php
                                                                                    $tiposDocumentos = obtenerTiposDocumentosPorPestaña($archivo['pestaña']);
                                                                                    $todosTipos = array_merge($tiposDocumentos['obligatorios'], $tiposDocumentos['opcionales']);
                                                                                    $nombreTipo = $todosTipos[$archivo['tipo_documento']] ?? $archivo['tipo_documento'];
                                                                                    ?>
                                                                                    <span
                                                                                        style="display: inline-block; padding: 3px 8px; background: <?= $archivo['obligatorio'] ? '#28a745' : '#6c757d' ?>; color: white; border-radius: 12px; font-size: 0.8em;">
                                                                                        <?= htmlspecialchars($nombreTipo) ?>
                                                                                        <?= $archivo['obligatorio'] ? ' *' : '' ?>
                                                                                    </span>
                                                                                <?php else: ?>
                                                                                    <span style="color: #6c757d; font-style: italic;">Sin
                                                                                        tipo</span>
                                                                                <?php endif; ?>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td style="padding: 12px;">
                                                                            <?php if ($archivo['tipo'] === 'faltante'): ?>
                                                                                <div style="font-weight: 500; color: #dc3545;">
                                                                                    <i class="fas fa-exclamation-circle"></i>
                                                                                    <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <div style="font-weight: 500;">
                                                                                    <?= htmlspecialchars($archivo['nombre_archivo']) ?>
                                                                                </div>
                                                                                <?php if (!empty($archivo['descripcion'])): ?>
                                                                                    <div
                                                                                        style="font-size: 0.9em; color: #6c757d; margin-top: 3px; display:none;">
                                                                                        <?= htmlspecialchars($archivo['descripcion']) ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td style="padding: 12px;">
                                                                            <?php if ($archivo['tipo'] === 'faltante'): ?>
                                                                                <div style="font-weight: 500; color: #dc3545;">
                                                                                    <i class="fas fa-exclamation-circle"></i>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <?php if (!empty($archivo['descripcion'])): ?>
                                                                                    <div style="font-size: 0.9em; color: #6c757d; margin-top: 3px;">
                                                                                        <?= htmlspecialchars($archivo['descripcion']) ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td style="padding: 12px;">
                                                                            <span
                                                                                style="display: inline-block; padding: 3px 8px; background: #e9ecef; color: #495057; border-radius: 12px; font-size: 0.8em;">
                                                                                <?= obtenerNombrePestaña($archivo['pestaña']) ?>
                                                                            </span>
                                                                        </td>
                                                                        <td style="padding: 12px;">
                                                                            <?= $archivo['tipo'] === 'faltante' ? 'No subido' : htmlspecialchars($archivo['nombre_usuario'] . ' ' . $archivo['apellido_usuario']) ?>
                                                                        </td>
                                                                        <td style="padding: 12px;">
                                                                            <?= $archivo['tipo'] === 'faltante' ? 'Pendiente' : date('d/m/Y H:i', strtotime($archivo['fecha_subida'])) ?>
                                                                        </td>
                                                                        <td style="padding: 12px; text-align: center;">
                                                                            <?php if ($archivo['tipo'] !== 'faltante'): ?>
                                                                                <a href="<?= htmlspecialchars($archivo['ruta_archivo']) ?>"
                                                                                    target="_blank" class="btn-accion btn-editar"
                                                                                    title="Ver documento">
                                                                                    <i class="fas fa-eye"></i>
                                                                                </a>
                                                                            <?php else: ?>
                                                                                <span style="color: #6c757d;">-</span>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                                            <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                            <p>No hay documentos en el expediente digital</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pestaña de Bitácora -->
                        <?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
                            <div id="bitacora" class="tab-pane <?= $pestaña_activa == 'bitacora' ? 'active' : '' ?>">
                                <div style="margin-bottom: 30px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">Nueva Anotación</h3>

                                    <form method="POST" action="">
                                        <input type="hidden" name="accion_bitacora" value="agregar">
                                        <input type="hidden" name="pestaña" value="bitacora">

                                        <div class="form-group">
                                            <label for="anotacion">Anotación *</label>
                                            <textarea id="anotacion" name="anotacion" class="form-control" rows="5"
                                                placeholder="Escriba aquí cualquier nota, observación o comentario sobre el colaborador..."
                                                required></textarea>
                                            <small style="color: #6c757d;">
                                                Esta anotación quedará registrada permanentemente y no podrá ser editada
                                                ni
                                                eliminada.
                                            </small>
                                        </div>

                                        <button type="submit" class="btn-submit">
                                            <i class="fas fa-save"></i> Guardar Anotación
                                        </button>
                                    </form>
                                </div>

                                <div style="border-top: 2px solid #0E544C; padding-top: 20px;">
                                    <h3 style="color: #0E544C; margin-bottom: 15px;">
                                        Historial de Bitácora
                                        <span style="font-size: 0.8em; color: #6c757d;">(<?= count($bitacoraColaborador) ?>
                                            anotaciones)</span>
                                    </h3>

                                    <?php if (count($bitacoraColaborador) > 0): ?>
                                        <div style="display: flex; flex-direction: column; gap: 15px;">
                                            <?php foreach ($bitacoraColaborador as $anotacion): ?>
                                                <div
                                                    style="background: #f8f9fa; border-left: 4px solid #0E544C; padding: 15px; border-radius: 4px;">
                                                    <div
                                                        style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                                        <div>
                                                            <strong style="color: #0E544C;">
                                                                <i class="fas fa-user"></i>
                                                                <?= htmlspecialchars($anotacion['nombre_usuario']) ?>
                                                            </strong>
                                                        </div>
                                                        <div style="color: #6c757d; font-size: 0.9em;">
                                                            <i class="fas fa-calendar"></i>
                                                            <?= date('d/m/Y H:i', strtotime($anotacion['fecha_registro'])) ?>
                                                        </div>
                                                    </div>
                                                    <div style="color: #333; line-height: 1.6; white-space: pre-wrap;">
                                                        <?= htmlspecialchars($anotacion['anotacion']) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center; padding: 40px; color: #6c757d;">
                                            <i class="fas fa-clipboard"
                                                style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                                            <p>No hay anotaciones en la bitácora</p>
                                            <p style="font-size: 0.9em;">Las anotaciones aparecerán aquí una vez que se
                                                registren</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Modal para agregar/editar cuenta bancaria -->
            <div id="modalCuenta" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;" id="tituloModalCuenta">Agregar Cuenta Bancaria
                    </h3>

                    <form method="POST" action="" id="formCuenta">
                        <input type="hidden" name="accion_cuenta" id="accionCuenta" value="agregar">
                        <input type="hidden" name="id_cuenta" id="idCuenta" value="">

                        <div class="form-group">
                            <label for="numero_cuenta_modal">Número de Cuenta</label>
                            <input type="text" id="numero_cuenta_modal" name="numero_cuenta" class="form-control"
                                maxlength="9" required>
                        </div>

                        <div class="form-group">
                            <label for="titular_modal">Titular</label>
                            <input type="text" id="titular_modal" name="titular" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="banco_modal">Banco</label>
                            <select id="banco_modal" name="banco" class="form-control" required>
                                <option value="Lafise" selected>Lafise</option>
                            </select>
                            <input type="hidden" name="banco" value="Lafise">
                        </div>

                        <div class="form-group">
                            <label for="moneda_modal">Moneda</label>
                            <select id="moneda_modal" name="moneda" class="form-control" required>
                                <option value="NIO" selected>Córdobas (NIO)</option>
                            </select>
                            <input type="hidden" name="moneda" value="NIO">
                        </div>

                        <div class="form-group">
                            <label for="desde_modal">Desde</label>
                            <input type="date" id="desde_modal" name="desde" class="form-control" required>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalCuenta()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para agregar/editar contacto de emergencia -->
            <div id="modalContacto" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;" id="tituloModalContacto">Agregar Contacto de
                        Emergencia
                    </h3>

                    <form method="POST" action="" id="formContacto">
                        <input type="hidden" name="accion_contacto" id="accionContacto" value="agregar">
                        <input type="hidden" name="id_contacto" id="idContacto" value="">

                        <div class="form-group">
                            <label for="nombre_contacto_modal">Nombre</label>
                            <input type="text" id="nombre_contacto_modal" name="nombre_contacto" class="form-control"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="parentesco_modal">Parentesco</label>
                            <input type="text" id="parentesco_modal" name="parentesco" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="telefono_movil_modal">Teléfono Móvil</label>
                            <input type="text" id="telefono_movil_modal" name="telefono_movil" class="form-control"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="telefono_casa_modal">Teléfono de Casa</label>
                            <input type="text" id="telefono_casa_modal" name="telefono_casa" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="telefono_trabajo_modal">Teléfono de Trabajo</label>
                            <input type="text" id="telefono_trabajo_modal" name="telefono_trabajo" class="form-control">
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalContacto()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para terminación de contrato -->
            <div id="modalTerminacion" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #dc3545; margin-bottom: 20px;">Terminar Contrato</h3>

                    <form method="POST" action="" enctype="multipart/form-data" id="formTerminacion">
                        <input type="hidden" name="pestaña" value="contrato">
                        <input type="hidden" name="accion_contrato" value="terminar"> <!-- CAMBIADO -->
                        <input type="hidden" name="id_contrato" id="idContratoTerminar"
                            value="<?= $contratoActual ? $contratoActual['CodContrato'] : '' ?>">

                        <!-- Fecha Fin de Contrato - SOLO LECTURA -->
                        <div class="form-group">
                            <label for="fecha_fin_contrato">Fecha Fin de Contrato (solo lectura)</label>
                            <input type="date" id="fecha_fin_contrato" name="fecha_fin_contrato" class="form-control"
                                value="<?= $contratoActual ? ($contratoActual['fin_contrato'] ?? '') : '' ?>" readonly
                                style="background-color: #f8f9fa;">
                            <small style="color: #6c757d;">Esta fecha no se puede modificar al terminar el
                                contrato</small>
                        </div>

                        <div class="form-group">
                            <label for="fecha_terminacion">Fecha de Salida/Terminación *</label>
                            <input type="date" id="fecha_terminacion" name="fecha_terminacion" class="form-control"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="fecha_liquidacion">Fecha de Liquidación (opcional - puede asignarse
                                después)</label>
                            <input type="date" id="fecha_liquidacion" name="fecha_liquidacion" class="form-control">
                            <small style="color: #6c757d; display:none;">Fecha cuando se realizará el pago de
                                liquidación</small>
                        </div>

                        <div class="form-group">
                            <label for="tipo_salida">Tipo de Salida *</label>
                            <select id="tipo_salida" name="tipo_salida" class="form-control" required>
                                <option value="">Seleccionar tipo de salida...</option>
                                <?php
                                $tiposSalida = obtenerTiposSalida();
                                foreach ($tiposSalida as $tipo): ?>
                                    <option value="<?= $tipo['CodTipoSalida'] ?>">
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="motivo_salida">Motivo de Salida *</label>
                            <textarea id="motivo_salida" name="motivo_salida" class="form-control" rows="3"
                                required></textarea>
                        </div>

                        <div style="display:none;" class="form-group">
                            <label for="foto_renuncia">Foto de Renuncia (opcional)</label>
                            <input type="file" id="foto_renuncia" name="foto_renuncia" class="form-control"
                                accept="image/*,.pdf">
                        </div>

                        <div style="display:none;" class="form-group">
                            <label for="devolucion_herramientas">Devolución de Herramientas de Trabajo</label>
                            <select id="devolucion_herramientas" name="devolucion_herramientas" class="form-control">
                                <option value="0">No aplica</option>
                                <option value="1">Sí aplica</option>
                            </select>
                        </div>

                        <div class="form-group" id="grupoPersonaHerramientas" style="display: none;">
                            <label for="persona_recibe_herramientas">Persona que Recibe Herramientas</label>
                            <input type="text" id="persona_recibe_herramientas" name="persona_recibe_herramientas"
                                class="form-control">
                        </div>

                        <div style="display:none;" class="form-group">
                            <label for="dias_trabajados">Días Trabajados *</label>
                            <input type="number" id="dias_trabajados" name="dias_trabajados" class="form-control"
                                min="1" required>
                        </div>

                        <div style="display:none;" class="form-group">
                            <label for="monto_indemnizacion">Indemnización</label>
                            <input type="number" id="monto_indemnizacion" name="monto_indemnizacion"
                                class="form-control" step="0.01" min="0">
                            <small style="color: #6c757d;">Monto en córdobas (opcional)</small>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalTerminacion()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit" style="background-color: #dc3545;">Confirmar
                                Terminación</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para agregar/editar salario -->
            <div id="modalSalario" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;" id="tituloModalSalario">Agregar Salario</h3>

                    <form method="POST" action="" id="formSalario">
                        <input type="hidden" name="accion_salario" id="accionSalario" value="agregar">
                        <input type="hidden" name="id_salario" id="idSalario" value="">

                        <div class="form-group">
                            <label for="monto_modal">Monto (C$)</label>
                            <input type="number" id="monto_modal" name="monto" class="form-control" step="0.01" min="0"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="inicio_modal">Desde</label>
                            <input type="date" id="inicio_modal" name="inicio" class="form-control" required>
                        </div>

                        <div class="form-group" style="display: none;">
                            <label for="fin_modal">Hasta (opcional)</label>
                            <input type="date" id="fin_modal" name="fin" class="form-control">
                            <small style="color: #6c757d;">Dejar vacío si es el salario actual</small>
                        </div>

                        <div class="form-group">
                            <label for="frecuencia_pago_modal">Frecuencia de Pago</label>
                            <select id="frecuencia_pago_modal" name="frecuencia_pago" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                <option value="quincenal">Quincenal</option>
                                <option value="mensual">Mensual</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="observaciones_modal">Observaciones (opcional)</label>
                            <textarea id="observaciones_modal" name="observaciones" class="form-control"
                                rows="3"></textarea>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalSalario()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para agregar archivos adjuntos -->
            <div id="modalAdjunto" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;">Agregar Archivo Adjunto</h3>

                    <form method="POST" action="" enctype="multipart/form-data" id="formAdjunto">
                        <input type="hidden" name="accion_adjunto" value="agregar">
                        <input type="hidden" name="pestaña_adjunto" id="pestañaAdjunto" value="">
                        <input type="hidden" name="cod_adendum_asociado" id="codAdendumAsociado" value="">

                        <div class="form-group">
                            <label for="tipo_documento_adjunto">Tipo de Documento *</label>
                            <select id="tipo_documento_adjunto" name="tipo_documento" class="form-control" required
                                onchange="actualizarDescripcionPorTipo()">
                                <option value="">Seleccionar tipo de documento...</option>
                                <!-- Las opciones se llenarán dinámicamente con JavaScript -->
                            </select>
                            <small id="ayudaTipoDocumento" style="color: #6c757d; display: none;"></small>
                        </div>

                        <div class="form-group">
                            <label for="archivo_adjunto">Archivo PDF (máximo 10MB) *</label>
                            <input type="file" id="archivo_adjunto" name="archivo_adjunto" class="form-control"
                                accept=".pdf" required>
                        </div>

                        <div class="form-group">
                            <label for="descripcion_adjunto">Descripción (opcional)</label>
                            <textarea id="descripcion_adjunto" name="descripcion_adjunto" class="form-control" rows="3"
                                placeholder="Breve descripción del archivo"></textarea>
                            <small style="color: #6c757d;">Para documentos obligatorios, la descripción se
                                completará
                                automáticamente.</small>
                        </div>

                        <div id="infoDocumentoObligatorio"
                            style="display: none; background-color: #e8f5e8; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                            <i class="fas fa-info-circle" style="color: #0E544C;"></i>
                            <span style="color: #0E544C; font-weight: bold;">Documento Obligatorio</span>
                            <p style="margin: 5px 0 0 0; color: #2d5016;" id="textoObligatorio"></p>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalAdjunto()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Subir Archivo</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para agregar Salario INSS -->
            <div id="modalSalarioINSS" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;">Agregar Salario INSS</h3>

                    <form method="POST" action="" id="formSalarioINSS">
                        <input type="hidden" name="accion_inss" value="agregar">
                        <input type="hidden" name="pestaña" value="inss">

                        <div class="form-group">
                            <label for="monto_salario_inss_modal">Salario INSS (C$)</label>
                            <input type="number" id="monto_salario_inss_modal" name="monto_salario_inss"
                                class="form-control" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label for="inicio_inss_modal">Inicio INSS</label>
                            <input type="date" id="inicio_inss_modal" name="inicio" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="observaciones_inss_modal">Observaciones</label>
                            <textarea id="observaciones_inss_modal" name="observaciones_inss" class="form-control"
                                rows="3"></textarea>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalSalarioINSS()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para editar movimiento de cargo -->
            <div id="modalMovimiento" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;">Editar Movimiento de Cargo</h3>

                    <form method="POST" action="" id="formMovimiento">
                        <input type="hidden" name="accion_movimiento" value="editar">
                        <input type="hidden" name="id_movimiento" id="idMovimiento" value="">
                        <input type="hidden" name="pestaña" value="movimientos">

                        <div class="form-group">
                            <label for="edit_cod_cargo">Cargo *</label>
                            <select id="edit_cod_cargo" name="cod_cargo" class="form-control" required>
                                <option value="">Seleccionar cargo...</option>
                                <?php foreach ($cargosDisponibles as $cargo): ?>
                                    <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                        <?= htmlspecialchars($cargo['Nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_sucursal">Sucursal *</label>
                            <select id="edit_sucursal" name="sucursal" class="form-control" required>
                                <option value="">Seleccionar sucursal...</option>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>">
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="edit_fecha_inicio" name="fecha_inicio" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_fecha_fin">Fecha de Fin (opcional)</label>
                            <input type="date" id="edit_fecha_fin" name="fecha_fin" class="form-control">
                        </div>

                        <!-- Tipo de contrato oculto, no se muestra ni edita -->
                        <input type="hidden" name="tipo_contrato" id="edit_tipo_contrato" value="">

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalMovimiento()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal de previsualización -->
            <div id="previewModal" class="preview-modal">
                <div class="preview-content">
                    <h3 class="preview-title">Previsualización de foto de perfil</h3>
                    <img id="previewImage" class="preview-image" src="" alt="Vista previa">
                    <p>¿Deseas usar esta imagen como tu foto de perfil?</p>
                    <div class="preview-buttons">
                        <button class="btn-cancel" onclick="cancelarPreview()">Cancelar</button>
                        <button class="btn-confirm" onclick="confirmarFoto()">Sí, usar esta foto</button>
                    </div>
                </div>
            </div>

            <!-- Modal para finalizar adenda -->
            <div id="modalFinalizarAdenda" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #dc3545; margin-bottom: 20px;">Finalizar Adenda</h3>

                    <form method="POST" action="" id="formFinalizarAdenda">
                        <input type="hidden" name="accion_finalizar_adenda" value="finalizar">
                        <input type="hidden" name="id_adendum_finalizar" id="idAdendumFinalizar" value="">
                        <input type="hidden" name="pestaña" value="adendums">

                        <div class="form-group">
                            <label for="fecha_fin_adenda">Fecha de Finalización *</label>
                            <input type="date" id="fecha_fin_adenda" name="fecha_fin_adenda" class="form-control"
                                value="<?= date('Y-m-d') ?>" required>
                            <small style="color: #6c757d;">Fecha cuando finaliza esta adenda</small>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalFinalizarAdenda()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit" style="background-color: #dc3545;">
                                Finalizar Adenda
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para editar categoría -->
            <div id="modalCategoria" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;">Editar Categoría</h3>

                    <form method="POST" action="" id="formCategoria">
                        <input type="hidden" name="accion_categoria" value="editar">
                        <input type="hidden" name="id_categoria_edit" id="idCategoriaEdit" value="">
                        <input type="hidden" name="pestaña" value="categoria">

                        <div class="form-group">
                            <label for="edit_id_categoria">Categoría *</label>
                            <select id="edit_id_categoria" name="id_categoria" class="form-control" required>
                                <option value="">Seleccionar categoría...</option>
                                <?php foreach ($todasCategorias as $categoria): ?>
                                    <option value="<?= $categoria['idCategoria'] ?>">
                                        <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                        (Peso: <?= $categoria['Peso'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_fecha_inicio">Fecha de Inicio *</label>
                            <input type="date" id="edit_fecha_inicio" name="fecha_inicio" class="form-control" required>
                        </div>

                        <div style="display:none;" class="form-group">
                            <label for="edit_fecha_fin">Fecha de Fin</label>
                            <input type="date" id="edit_fecha_fin" name="fecha_fin" class="form-control">
                            <small style="color: #6c757d;">Dejar vacío si es la categoría actual</small>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalCategoria()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para editar adendum -->
            <div id="modalAdendum" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;">Editar Adendum</h3>

                    <form method="POST" action="" id="formAdendum">
                        <input type="hidden" name="accion_adendum" value="editar">
                        <input type="hidden" name="id_adendum" id="edit_id_adendum" value="">
                        <input type="hidden" name="pestaña" value="adendums">

                        <div class="form-group">
                            <label for="edit_tipo_adendum">Tipo de Adendum *</label>
                            <select id="edit_tipo_adendum" name="tipo_adendum" class="form-control" required
                                onchange="actualizarCamposEdicionAdendum()">
                                <option value="">Seleccionar tipo...</option>
                                <option value="cargo">Cambio de Cargo</option>
                                <option value="salario">Ajuste Salarial</option>
                                <option value="ambos">Cambio de Cargo y Salario</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group" id="edit_grupo_cargo">
                                    <label for="edit_cod_cargo_adendum">Cargo</label>
                                    <select id="edit_cod_cargo_adendum" name="cod_cargo" class="form-control">
                                        <option value="">Seleccionar cargo...</option>
                                        <?php foreach ($cargosDisponibles as $cargo): ?>
                                            <option value="<?= $cargo['CodNivelesCargos'] ?>">
                                                <?= htmlspecialchars($cargo['Nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" id="edit_grupo_sucursal">
                                    <label for="edit_sucursal_adendum">Sucursal</label>
                                    <select id="edit_sucursal_adendum" name="sucursal" class="form-control">
                                        <option value="">Seleccionar sucursal...</option>
                                        <?php foreach ($sucursales as $sucursal): ?>
                                            <option value="<?= $sucursal['codigo'] ?>">
                                                <?= htmlspecialchars($sucursal['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-col">
                                <div class="form-group" id="edit_grupo_categoria">
                                    <label for="edit_id_categoria_adendum">Categoría</label>
                                    <select id="edit_id_categoria_adendum" name="id_categoria" class="form-control">
                                        <option value="">Seleccionar categoría...</option>
                                        <?php foreach ($todasCategorias as $categoria): ?>
                                            <option value="<?= $categoria['idCategoria'] ?>">
                                                <?= htmlspecialchars($categoria['NombreCategoria']) ?>
                                                (Peso: <?= $categoria['Peso'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" id="edit_grupo_salario">
                                    <label for="edit_salario_adendum">Salario (C$)</label>
                                    <input type="number" id="edit_salario_adendum" name="salario" class="form-control"
                                        step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_fecha_inicio_adendum">Fecha de Inicio *</label>
                            <input type="date" id="edit_fecha_inicio_adendum" name="fecha_inicio" class="form-control"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="edit_fecha_fin_adendum">Fecha de Fin</label>
                            <input type="date" id="edit_fecha_fin_adendum" name="fecha_fin" class="form-control">
                            <small style="color: #6c757d;">Dejar vacío si es el adendum actual</small>
                        </div>

                        <div class="form-group">
                            <label for="edit_observaciones_adendum">Observaciones</label>
                            <textarea id="edit_observaciones_adendum" name="observaciones" class="form-control"
                                rows="3"></textarea>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalAdendum()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal para asignar fecha de liquidación -->
            <div id="modalLiquidacion" class="modal-backdrop">
                <div class="modal-content">
                    <h3 style="color: #0E544C; margin-bottom: 20px;">Asignar Fecha de Liquidación</h3>

                    <form method="POST" action="" id="formLiquidacion">
                        <input type="hidden" name="accion_liquidacion" value="asignar">
                        <input type="hidden" name="id_contrato_liquidacion" id="idContratoLiquidacion" value="">

                        <div class="form-group">
                            <label for="fecha_liquidacion_modal">Fecha de Liquidación *</label>
                            <input type="date" id="fecha_liquidacion_modal" name="fecha_liquidacion"
                                class="form-control" required>
                            <small style="color: #6c757d;">Asigne la fecha cuando se realizará el pago de
                                liquidación</small>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalLiquidacion()"
                                style="background-color: #6c757d;">Cancelar</button>
                            <button type="submit" class="btn-submit">Guardar Fecha</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                // Función para ocultar mensajes automáticamente después de 5 segundos
                function ocultarMensajesAutomaticamente() {
                    const mensajes = document.querySelectorAll('.alert');
                    mensajes.forEach(mensaje => {
                        setTimeout(() => {
                            mensaje.style.transition = 'opacity 0.5s ease';
                            mensaje.style.opacity = '0';
                            setTimeout(() => {
                                mensaje.remove();
                            }, 500);
                        }, 5000); // 5 segundos
                    });
                }

                // Función para actualizar categoría automáticamente según el cargo seleccionado
                function actualizarCategoria() {
                    const codCargo = document.getElementById('cod_cargo').value;
                    const selectCategoria = document.getElementById('id_categoria');

                    if (codCargo == '2') {
                        // Si es cargo 2 (Operario), seleccionar categoría 5
                        for (let i = 0; i < selectCategoria.options.length; i++) {
                            if (selectCategoria.options[i].value == '5') {
                                selectCategoria.value = '5';
                                break;
                            }
                        }
                    } else if (codCargo == '5') {
                        // Si es cargo 5 (Líder de Sucursal), seleccionar categoría 1
                        for (let i = 0; i < selectCategoria.options.length; i++) {
                            if (selectCategoria.options[i].value == '1') {
                                selectCategoria.value = '1';
                                break;
                            }
                        }
                    }
                    // Para otros cargos, no se selecciona automáticamente
                }

                // Función mejorada para abrir modal de terminación
                function abrirModalTerminacion() {
                    document.getElementById('modalTerminacion').style.display = 'block';

                    // Obtener información del contrato actual
                    const contratoActual = <?= $contratoActual ? json_encode($contratoActual) : 'null' ?>;

                    if (contratoActual) {
                        // Establecer el ID del contrato en el formulario
                        document.getElementById('idContratoTerminar').value = contratoActual.CodContrato;

                        // Mostrar fecha fin del contrato (solo lectura)
                        if (contratoActual.fin_contrato && contratoActual.fin_contrato != '0000-00-00') {
                            document.getElementById('fecha_fin_contrato').value = contratoActual.fin_contrato;
                        } else {
                            document.getElementById('fecha_fin_contrato').value = '';
                            document.getElementById('fecha_fin_contrato').placeholder = 'Contrato indefinido';
                        }

                        // Calcular días trabajados automáticamente desde inicio_contrato hasta fecha actual
                        const inicioContrato = contratoActual.inicio_contrato;
                        if (inicioContrato) {
                            const inicio = new Date(inicioContrato);
                            const hoy = new Date();
                            const diffTime = Math.abs(hoy - inicio);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            document.getElementById('dias_trabajados').value = diffDays;
                        }
                    }

                    // Establecer fecha de hoy como fecha de terminación por defecto
                    document.getElementById('fecha_terminacion').valueAsDate = new Date();
                }

                // Función para confirmar la terminación con validación
                function confirmarTerminacion() {
                    const fechaTerminacion = document.getElementById('fecha_terminacion').value;
                    const tipoSalida = document.getElementById('tipo_salida').value;
                    const motivoSalida = document.getElementById('motivo_salida').value;

                    if (!fechaTerminacion || !tipoSalida || !motivoSalida.trim()) {
                        alert('Por favor complete todos los campos obligatorios.');
                        return false;
                    }

                    if (confirm('¿Está seguro de que desea terminar este contrato? Esta acción cerrará todos los registros activos del colaborador y no se puede deshacer.')) {
                        return true;
                    }

                    return false;
                }

                function cerrarModalTerminacion() {
                    const modal = document.getElementById('modalTerminacion');
                    const form = document.getElementById('formTerminacion');

                    // Restaurar título
                    document.querySelector('#modalTerminacion h3').textContent = 'Terminar Contrato';

                    // Restaurar acción a "terminar"
                    const inputAccion = form.querySelector('input[name="accion_contrato"]');
                    inputAccion.value = 'terminar';

                    // Rehabilitar id_contrato original
                    const inputIdOriginal = form.querySelector('input[name="id_contrato"]');
                    if (inputIdOriginal) {
                        inputIdOriginal.disabled = false;
                    }

                    // Eliminar id_contrato_editar si existe
                    const inputIdEditar = form.querySelector('input[name="id_contrato_editar"]');
                    if (inputIdEditar) {
                        inputIdEditar.remove();
                    }

                    // Restaurar texto del botón
                    const btnSubmit = form.querySelector('button[type="submit"]');
                    btnSubmit.textContent = 'Confirmar Terminación';
                    btnSubmit.style.backgroundColor = '#dc3545'; // Restaurar color rojo

                    // Cerrar modal
                    modal.style.display = 'none';
                }

                // Asignar el evento de confirmación al formulario
                document.getElementById('formTerminacion').addEventListener('submit', function (e) {
                    if (!confirmarTerminacion()) {
                        e.preventDefault();
                    }
                });

                // Función para mostrar/ocultar contraseña
                function togglePasswordVisibility() {
                    const passwordInput = document.getElementById('clave');
                    const toggleButton = document.getElementById('toggleClave');
                    const icon = toggleButton.querySelector('i');

                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                        toggleButton.title = 'Ocultar contraseña';
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                        toggleButton.title = 'Mostrar contraseña';
                    }
                }

                // Función para actualizar y mostrar la categoría según el cargo seleccionado
                function actualizarCategoriaYMostrar() {
                    const codCargo = document.getElementById('cod_cargo');
                    const selectCategoria = document.getElementById('id_categoria');
                    const infoCategoria = document.getElementById('infoCategoria');
                    const textoCategoria = document.getElementById('textoCategoria');

                    // Ocultar información por defecto
                    infoCategoria.style.display = 'none';

                    if (codCargo.value) {
                        const optionSeleccionada = codCargo.options[codCargo.selectedIndex];
                        const idCategoriaSugerida = optionSeleccionada.getAttribute('data-categoria');

                        if (idCategoriaSugerida) {
                            // Buscar y seleccionar la categoría sugerida
                            for (let i = 0; i < selectCategoria.options.length; i++) {
                                if (selectCategoria.options[i].value == idCategoriaSugerida) {
                                    selectCategoria.value = idCategoriaSugerida;
                                    const nombreCategoria = selectCategoria.options[i].text.split(' (Peso:')[0]; // Remover el peso
                                    textoCategoria.textContent = 'Categoría asignada automáticamente: ' + nombreCategoria;
                                    infoCategoria.style.display = 'block';
                                    break;
                                }
                            }
                        }// else {
                        // Para cargos sin categoría predefinida
                        //textoCategoria.textContent = 'Seleccione una categoría manualmente para este cargo';
                        //infoCategoria.style.display = 'block';
                        //selectCategoria.value = ''; // Limpiar selección
                        //}
                    }
                }

                // También mostrar la categoría actual al cargar la página
                document.addEventListener('DOMContentLoaded', function () {
                    const codCargo = document.getElementById('cod_cargo');
                    if (codCargo && codCargo.value) {
                        actualizarCategoriaYMostrar();
                    }

                    // Mapeo de cargos a categorías para referencia
                    window.mapaCargosCategorias = {
                        '2': { id: 5, nombre: 'Operario' },    // Cargo Operario -> Categoría Operario (id 5)
                        '5': { id: 1, nombre: 'Líder' }        // Cargo Líder -> Categoría Líder (id 1)
                        // Agregar más mapeos según necesites
                    };
                });

                // Función para obtener información de categoría por cargo
                function obtenerInfoCategoriaPorCargo(codCargo) {
                    const mapa = window.mapaCargosCategorias || {};
                    return mapa[codCargo] || null;
                }

                // Modificar la función existente
                function toggleFechaFinContrato() {
                    const tipoContrato = document.getElementById('cod_tipo_contrato').value;
                    const grupoFechaFin = document.getElementById('grupo_fecha_fin_contrato');
                    const inputFechaFin = document.getElementById('fin_contrato');

                    if (tipoContrato == '1') { // Contrato temporal
                        grupoFechaFin.style.display = 'block';
                        inputFechaFin.disabled = false;
                        inputFechaFin.required = true;
                    } else if (tipoContrato == '2') { // Contrato indefinido - NUEVO
                        grupoFechaFin.style.display = 'block';
                        inputFechaFin.disabled = true;
                        inputFechaFin.required = false;
                        inputFechaFin.value = ''; // Limpiar el valor visualmente
                        inputFechaFin.placeholder = 'No aplica para contratos indefinidos';
                    } else {
                        grupoFechaFin.style.display = 'block';
                        inputFechaFin.disabled = true;
                        inputFechaFin.required = false;
                    }
                }

                // Mostrar/ocultar campo de persona que recibe herramientas
                document.getElementById('devolucion_herramientas').addEventListener('change', function () {
                    const grupoPersona = document.getElementById('grupoPersonaHerramientas');
                    grupoPersona.style.display = this.value == '1' ? 'block' : 'none';
                });

                // Cerrar modal al hacer clic fuera
                document.getElementById('modalTerminacion').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalTerminacion();
                    }
                });

                // Ejecutar cuando el documento esté cargado
                document.addEventListener('DOMContentLoaded', function () {
                    ocultarMensajesAutomaticamente();

                    const toggleButton = document.getElementById('toggleClave');
                    if (toggleButton) {
                        toggleButton.addEventListener('click', togglePasswordVisibility);
                    }

                    // Actualizar categoría al cargar la página si ya hay un cargo seleccionado
                    const codCargo = document.getElementById('cod_cargo');
                    if (codCargo) {
                        actualizarCategoria();
                    }

                    //const selectTipoContrato = document.getElementById('cod_tipo_contrato');
                    //if (selectTipoContrato) {
                    //    toggleFechaFinContrato();
                    //    selectTipoContrato.addEventListener('change', toggleFechaFinContrato);
                    //}

                    const selectTipoContrato = document.getElementById('cod_tipo_contrato');
                    if (selectTipoContrato) {
                        toggleFechaFinContrato(); // Llamar al cargar
                        selectTipoContrato.addEventListener('change', toggleFechaFinContrato); // Ya existe

                        // AGREGAR ESTE CÓDIGO NUEVO:
                        // Detectar cuando cambia a tipo 2 (indefinido) y limpiar fecha fin
                        selectTipoContrato.addEventListener('change', function () {
                            if (this.value == '2') {
                                const inputFechaFin = document.getElementById('fin_contrato');
                                inputFechaFin.value = ''; // Limpiar visualmente

                                // Mostrar mensaje informativo (opcional)
                                console.log('Tipo de contrato cambiado a Indefinido. Fecha fin será eliminada al guardar.');
                            }
                        });
                    }

                    // También permitir cerrar mensajes haciendo clic en ellos
                    document.querySelectorAll('.alert').forEach(mensaje => {
                        mensaje.style.cursor = 'pointer';
                        mensaje.addEventListener('click', function () {
                            this.style.transition = 'opacity 0.5s ease';
                            this.style.opacity = '0';
                            setTimeout(() => {
                                this.remove();
                            }, 500);
                        });
                    });
                });

                // Script para formatear automáticamente la cédula con guiones
                document.addEventListener('DOMContentLoaded', function () {
                    const cedulaInput = document.getElementById('cedula');

                    if (cedulaInput) {
                        cedulaInput.addEventListener('input', function () {
                            // Obtener valor sin guiones y mantener cualquier letra al final
                            let value = this.value.replace(/-/g, '');

                            // Guardar la posición del cursor
                            const startPos = this.selectionStart;

                            // Separar números y letra final si existe
                            let numbers = value.replace(/[^0-9]/g, '');
                            let letter = '';

                            // Verificar si hay una letra al final
                            if (value.length > 0 && /[A-Za-z]$/.test(value)) {
                                letter = value.slice(-1);
                                numbers = numbers.slice(0, numbers.length);
                            }

                            // Limitar a 13 números como máximo
                            if (numbers.length > 13) {
                                numbers = numbers.substring(0, 13);
                            }

                            // Aplicar el formato con guiones
                            let formattedValue = numbers;
                            if (numbers.length > 9) {
                                formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3, 9) + '-' + numbers.substring(9);
                            } else if (numbers.length > 3) {
                                formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3);
                            }

                            // Agregar la letra al final si existe
                            if (letter) {
                                formattedValue += letter;
                            }

                            // Actualizar el valor
                            this.value = formattedValue;

                            // Ajustar la posición del cursor
                            let adjustedPos = startPos;

                            // Si agregamos guiones antes de la posición actual, ajustar
                            if (startPos >= 3 && numbers.length >= 3) adjustedPos++;
                            if (startPos >= 9 && numbers.length >= 9) adjustedPos++;

                            // Asegurarse de que no exceda la longitud
                            if (adjustedPos > formattedValue.length) {
                                adjustedPos = formattedValue.length;
                            }

                            this.setSelectionRange(adjustedPos, adjustedPos);
                        });

                        // También formatear el valor inicial si existe
                        if (cedulaInput.value) {
                            // Disparar el evento input para formatear el valor existente
                            cedulaInput.dispatchEvent(new Event('input'));
                        }
                    }
                });

                // Funciones para el modal de cuentas bancarias
                function abrirModalCuenta() {
                    document.getElementById('modalCuenta').style.display = 'block';
                    document.getElementById('tituloModalCuenta').textContent = 'Agregar Cuenta Bancaria';
                    document.getElementById('accionCuenta').value = 'agregar';
                    document.getElementById('idCuenta').value = '';
                    document.getElementById('formCuenta').reset();
                }

                function editarCuenta(idCuenta) {
                    // Hacer una solicitud AJAX para obtener los datos de la cuenta
                    fetch(`obtener_cuenta.php?id=${idCuenta}`)
                        .then(response => response.json())
                        .then(cuenta => {
                            document.getElementById('modalCuenta').style.display = 'block';
                            document.getElementById('tituloModalCuenta').textContent = 'Editar Cuenta Bancaria';
                            document.getElementById('accionCuenta').value = 'editar';
                            document.getElementById('idCuenta').value = idCuenta;
                            document.getElementById('numero_cuenta_modal').value = cuenta.numero_cuenta || '';
                            document.getElementById('titular_modal').value = cuenta.titular || '';
                            document.getElementById('banco_modal').value = cuenta.banco || '';
                            document.getElementById('moneda_modal').value = cuenta.moneda || '';
                            document.getElementById('desde_modal').value = cuenta.desde || '';
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cargar los datos de la cuenta');
                        });
                }

                function cerrarModalCuenta() {
                    document.getElementById('modalCuenta').style.display = 'none';
                }

                // Funciones para el modal de contactos de emergencia
                function abrirModalContacto() {
                    document.getElementById('modalContacto').style.display = 'block';
                    document.getElementById('tituloModalContacto').textContent = 'Agregar Contacto de Emergencia';
                    document.getElementById('accionContacto').value = 'agregar';
                    document.getElementById('idContacto').value = '';
                    document.getElementById('formContacto').reset();
                }

                function editarContacto(idContacto) {
                    // Hacer una solicitud AJAX para obtener los datos del contacto
                    fetch(`obtener_contacto.php?id=${idContacto}`)
                        .then(response => response.json())
                        .then(contacto => {
                            document.getElementById('modalContacto').style.display = 'block';
                            document.getElementById('tituloModalContacto').textContent = 'Editar Contacto de Emergencia';
                            document.getElementById('accionContacto').value = 'editar';
                            document.getElementById('idContacto').value = idContacto;
                            document.getElementById('nombre_contacto_modal').value = contacto.nombre_contacto || '';
                            document.getElementById('parentesco_modal').value = contacto.parentesco || '';
                            document.getElementById('telefono_movil_modal').value = contacto.telefono_movil || '';
                            document.getElementById('telefono_casa_modal').value = contacto.telefono_casa || '';
                            document.getElementById('telefono_trabajo_modal').value = contacto.telefono_trabajo || '';
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cargar los datos del contacto');
                        });
                }

                function cerrarModalContacto() {
                    document.getElementById('modalContacto').style.display = 'none';
                }

                // Funciones para el modal de salarios
                function abrirModalSalario() {
                    document.getElementById('modalSalario').style.display = 'block';
                    document.getElementById('tituloModalSalario').textContent = 'Agregar Salario';
                    document.getElementById('accionSalario').value = 'agregar';
                    document.getElementById('idSalario').value = '';
                    document.getElementById('formSalario').reset();

                    // Establecer fecha de hoy como valor por defecto para "Desde"
                    document.getElementById('inicio_modal').valueAsDate = new Date();
                }

                function editarSalario(idSalario) {
                    // Hacer una solicitud AJAX para obtener los datos del salario
                    fetch(`obtener_salario.php?id=${idSalario}`)
                        .then(response => response.json())
                        .then(salario => {
                            document.getElementById('modalSalario').style.display = 'block';
                            document.getElementById('tituloModalSalario').textContent = 'Editar Salario';
                            document.getElementById('accionSalario').value = 'editar';
                            document.getElementById('idSalario').value = idSalario;
                            document.getElementById('monto_modal').value = salario.monto || '';
                            document.getElementById('inicio_modal').value = salario.inicio || '';
                            document.getElementById('fin_modal').value = salario.fin || '';
                            document.getElementById('frecuencia_pago_modal').value = salario.frecuencia_pago || '';
                            document.getElementById('observaciones_modal').value = salario.observaciones || '';
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cargar los datos del salario');
                        });
                }

                function cerrarModalSalario() {
                    document.getElementById('modalSalario').style.display = 'none';
                }

                // Cerrar modal de salario al hacer clic fuera del contenido
                document.getElementById('modalSalario').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalSalario();
                    }
                });

                // Cerrar modales al hacer clic fuera del contenido
                document.getElementById('modalCuenta').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalCuenta();
                    }
                });

                document.getElementById('modalContacto').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalContacto();
                    }
                });

                // Configuración de tipos de documentos por pestaña
                const tiposDocumentos = {
                    'datos-personales': {
                        obligatorios: [
                            { valor: 'record_ley_510', texto: 'Récord Ley 510' },
                            { valor: 'certificado_salud', texto: 'Certificado de Salud' },
                            { valor: 'constancia_judicial', texto: 'Constancia Judicial' },
                            { valor: 'soportes_estudios', texto: 'Soportes de Estudios' },
                            { valor: 'historial_inss', texto: 'Historial de INSS' },
                            { valor: 'cedula', texto: 'Cédula' }
                        ],
                        opcionales: [
                            { valor: 'hoja_vida', texto: 'Hoja de Vida' },
                            { valor: 'cartas_recomendacion', texto: 'Cartas de Recomendación Personal' },
                            { valor: 'soportes_empleos_anteriores', texto: 'Soportes de Empleos Anteriores' },
                            { valor: 'otro', texto: 'Otro Documento' }
                        ]
                    },
                    'inss': {
                        obligatorios: [
                            { valor: 'hoja_inscripcion_inss', texto: 'Hoja de Inscripción INSS' },
                        ],
                        opcionales: [
                            { valor: 'colilla_inss', texto: 'Colilla INSS' },
                            { valor: 'otro', texto: 'Otro Documento INSS' }
                        ]
                    },
                    'contrato': {
                        obligatorios: [
                            { valor: 'contrato_firmado', texto: 'Contrato Firmado' }
                        ],
                        opcionales: [
                            { valor: 'anexos_contrato', texto: 'Anexos del Contrato' },
                            { valor: 'otro', texto: 'Otro Documento' }
                        ]
                    },
                    'contactos-emergencia': {
                        obligatorios: [],
                        opcionales: [
                            { valor: 'formulario_contactos', texto: 'Formulario de Contactos de Emergencia' },
                            { valor: 'otro', texto: 'Otro Documento' }
                        ]
                    },
                    'salario': {
                        obligatorios: [],
                        opcionales: [
                            { valor: 'escalas_salariales', texto: 'Escalas Salariales' },
                            { valor: 'otro', texto: 'Otro Documento' }
                        ]
                    },
                    'movimientos': {
                        obligatorios: [],
                        opcionales: [
                            { valor: 'documentos_movimiento', texto: 'Documentos de Movimiento' },
                            { valor: 'otro', texto: 'Otro Documento' }
                        ]
                    },
                    'categoria': {
                        obligatorios: [],
                        opcionales: [
                            { valor: 'certificaciones', texto: 'Certificaciones' },
                            { valor: 'otro', texto: 'Otro Documento' }
                        ]
                    },
                    'adendums': {
                        obligatorios: [],
                        opcionales: [
                            { valor: 'adendums_firmados', texto: 'Adendums Firmados' },
                            { valor: 'otro', texto: 'Otro Documento' }
                        ]
                    }
                };

                // Función para abrir el modal de adjuntos con los tipos de documentos
                function abrirModalAdjunto(pestaña, codAdendum = null) {
                    // Verificar si requiere contrato y si existe
                    const pestañasRequierenContrato = ['contrato', 'adendums', 'inss', 'salario', 'movimientos', 'categoria'];
                    const tieneContrato = <?= tieneContratoActivo($codOperario) ? 'true' : 'false' ?>;

                    if (pestañasRequierenContrato.includes(pestaña) && !tieneContrato) {
                        alert('No se puede subir archivos en esta pestaña porque no hay un contrato activo. Complete la información del contrato primero.');
                        return;
                    }

                    // VALIDACIÓN CORREGIDA PARA ADENDUMS: Verificar que exista al menos un adendum
                    if (pestaña === 'adendums') {
                        const tieneAdendums = <?= count($adendumsColaborador) > 0 ? 'true' : 'false' ?>;
                        if (!tieneAdendums) {
                            alert('No se puede subir archivos en la pestaña Adendums porque no hay adendums registrados. Debe crear al menos un adendum primero.');
                            return;
                        }
                    }

                    // Si es pestaña de adendums y tenemos código de adendum, guardarlo
                    if (pestaña === 'adendums' && codAdendum) {
                        document.getElementById('codAdendumAsociado').value = codAdendum;
                    } else if (pestaña === 'adendums') {
                        // Si no se proporciona código, intentar obtener el último adendum activo
                        const ultimoAdendumId = obtenerUltimoAdendumActivoId();
                        if (ultimoAdendumId) {
                            document.getElementById('codAdendumAsociado').value = ultimoAdendumId;
                        }
                    }

                    document.getElementById('modalAdjunto').style.display = 'block';
                    document.getElementById('pestañaAdjunto').value = pestaña;
                    document.getElementById('formAdjunto').reset();

                    // Limpiar y llenar el select de tipos de documento
                    const selectTipo = document.getElementById('tipo_documento_adjunto');
                    selectTipo.innerHTML = '<option value="">Seleccionar tipo de documento...</option>';

                    const documentosPestaña = tiposDocumentos[pestaña] || { obligatorios: [], opcionales: [] };

                    // Agregar documentos obligatorios
                    if (documentosPestaña.obligatorios.length > 0) {
                        const optGroupObligatorios = document.createElement('optgroup');
                        optGroupObligatorios.label = 'Documentos Obligatorios';
                        documentosPestaña.obligatorios.forEach(doc => {
                            const option = document.createElement('option');
                            option.value = doc.valor;
                            option.textContent = doc.texto;
                            option.setAttribute('data-obligatorio', '1');
                            optGroupObligatorios.appendChild(option);
                        });
                        selectTipo.appendChild(optGroupObligatorios);
                    }

                    // Agregar documentos opcionales
                    if (documentosPestaña.opcionales.length > 0) {
                        const optGroupOpcionales = document.createElement('optgroup');
                        optGroupOpcionales.label = 'Documentos Opcionales';
                        documentosPestaña.opcionales.forEach(doc => {
                            const option = document.createElement('option');
                            option.value = doc.valor;
                            option.textContent = doc.texto;
                            option.setAttribute('data-obligatorio', '0');
                            optGroupOpcionales.appendChild(option);
                        });
                        selectTipo.appendChild(optGroupOpcionales);
                    }

                    // Ocultar/mostrar sección de obligatorios
                    document.getElementById('infoDocumentoObligatorio').style.display = 'none';
                }

                // Función mejorada para obtener el último adendum activo via AJAX
                function obtenerUltimoAdendumActivoId() {
                    return new Promise((resolve, reject) => {
                        fetch(`obtener_ultimo_adendum.php?cod_operario=<?= $codOperario ?>`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.exito && data.adendum) {
                                    resolve(data.adendum.id);
                                } else {
                                    resolve(null);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                resolve(null);
                            });
                    });
                }

                // Función para verificar si puede crear adendum
                function verificarPuedeCrearAdendum() {
                    const tieneContrato = <?= tieneContratoActivo($codOperario) ? 'true' : 'false' ?>;

                    if (!tieneContrato) {
                        return { puede: false, motivo: 'No hay contrato activo' };
                    }

                    return { puede: true, motivo: '' };
                }

                // Actualizar estado del formulario de adendum al cargar la página
                document.addEventListener('DOMContentLoaded', function () {
                    const estado = verificarPuedeCrearAdendum();
                    const formAdendum = document.querySelector('form[action*="pestaña=adendums"]');
                    const btnSubmit = formAdendum ? formAdendum.querySelector('button[type="submit"]') : null;

                    if (btnSubmit && !estado.puede) {
                        btnSubmit.disabled = true;
                        btnSubmit.title = 'No puede crear adendum: ' + estado.motivo;
                        btnSubmit.innerHTML = '<i class="fas fa-ban"></i> ' + estado.motivo;
                    }
                });

                // Función para actualizar la descripción según el tipo seleccionado
                function actualizarDescripcionPorTipo() {
                    const selectTipo = document.getElementById('tipo_documento_adjunto');
                    const descripcionInput = document.getElementById('descripcion_adjunto');
                    const infoObligatorio = document.getElementById('infoDocumentoObligatorio');
                    const textoObligatorio = document.getElementById('textoObligatorio');
                    const ayudaTipo = document.getElementById('ayudaTipoDocumento');

                    const valorSeleccionado = selectTipo.value;
                    const esObligatorio = selectTipo.options[selectTipo.selectedIndex]?.getAttribute('data-obligatorio') === '1';

                    if (valorSeleccionado) {
                        if (esObligatorio) {
                            infoObligatorio.style.display = 'block';
                            textoObligatorio.textContent = 'Este documento es requerido para completar la información del colaborador.';
                            ayudaTipo.style.display = 'block';
                            ayudaTipo.textContent = 'Documento obligatorio - solo puede subir uno de este tipo';
                            ayudaTipo.style.color = '#0E544C';
                        } else {
                            infoObligatorio.style.display = 'none';
                            ayudaTipo.style.display = 'block';
                            ayudaTipo.textContent = 'Documento opcional - puede subir múltiples archivos';
                            ayudaTipo.style.color = '#6c757d';
                        }

                        // Auto-completar descripción para tipos específicos
                        if (valorSeleccionado !== 'otro') {
                            descripcionInput.value = selectTipo.options[selectTipo.selectedIndex].textContent;
                        } else {
                            descripcionInput.value = '';
                        }
                    } else {
                        infoObligatorio.style.display = 'none';
                        ayudaTipo.style.display = 'none';
                        descripcionInput.value = '';
                    }
                }

                // Función para actualizar los íconos de estado en las pestañas
                function actualizarIconosEstadoPestanas() {
                    const pestañas = ['datos-personales', 'inss', 'contrato', 'contactos-emergencia',
                        'salario', 'movimientos', 'categoria', 'adendums'];

                    pestañas.forEach(pestaña => {
                        fetch(`obtener_estado_documentos.php?cod_operario=<?= $codOperario ?>&pestaña=${pestaña}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.estado && data.estado !== 'no_aplica') {
                                    const tabButton = document.querySelector(`.tab-button[href*="pestaña=${pestaña}"]`);
                                    if (tabButton) {
                                        // Remover ícono existente
                                        const iconoExistente = tabButton.querySelector('.estado-documentos');
                                        if (iconoExistente) {
                                            iconoExistente.remove();
                                        }

                                        // Agregar nuevo ícono
                                        const icono = document.createElement('span');
                                        icono.className = 'estado-documentos';
                                        icono.innerHTML = obtenerIconoPorEstado(data.estado);
                                        tabButton.appendChild(icono);
                                    }
                                }
                            })
                            .catch(error => console.error('Error al obtener estado:', error));
                    });
                }

                // Función auxiliar para obtener el ícono según el estado
                function obtenerIconoPorEstado(estado) {
                    const iconos = {
                        'completo': '<i class="fas fa-check-circle" style="color: #28a745; margin-left: 5px;"></i>',
                        'parcial': '<i class="fas fa-clock" style="color: #ffc107; margin-left: 5px;"></i>',
                        'pendiente': '<i class="fas fa-exclamation-circle" style="color: #dc3545; margin-left: 5px;"></i>'
                    };
                    return iconos[estado] || '';
                }

                // Llamar a la función cuando se cargue la página
                document.addEventListener('DOMContentLoaded', function () {
                    actualizarIconosEstadoPestanas();
                });

                function cerrarModalAdjunto() {
                    document.getElementById('modalAdjunto').style.display = 'none';
                }

                // Cerrar modal al hacer clic fuera
                //document.getElementById('modalAdjunto').addEventListener('click', function(e) {
                //    if (e.target === this) {
                //        cerrarModalAdjunto();
                //    }
                //});

                // Funciones para el modal de Salario INSS
                function abrirModalSalarioINSS() {
                    document.getElementById('modalSalarioINSS').style.display = 'block';
                    document.getElementById('formSalarioINSS').reset();

                    // Establecer fecha de hoy como valor por defecto
                    document.getElementById('inicio_inss_modal').valueAsDate = new Date();
                }

                function cerrarModalSalarioINSS() {
                    document.getElementById('modalSalarioINSS').style.display = 'none';

                    // Restablecer el formulario a modo agregar
                    const form = document.getElementById('formSalarioINSS');
                    form.querySelector('input[name="accion_inss"]').value = 'agregar';
                    const hiddenId = form.querySelector('input[name="id_salario_inss"]');
                    if (hiddenId) {
                        hiddenId.remove();
                    }
                    form.reset();
                }

                // Cerrar modal al hacer clic fuera
                document.getElementById('modalSalarioINSS').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalSalarioINSS();
                    }
                });

                // Variable global para almacenar la imagen temporal
                let imagenTemporal = null;

                // Cuando el documento esté cargado
                document.addEventListener('DOMContentLoaded', function () {
                    // Configurar el evento change del input file
                    const inputFoto = document.getElementById('inputFotoPerfil');
                    if (inputFoto) {
                        inputFoto.addEventListener('change', function (e) {
                            if (this.files && this.files[0]) {
                                imagenTemporal = this.files[0];
                                const reader = new FileReader();

                                reader.onload = function (e) {
                                    // Mostrar la previsualización
                                    document.getElementById('previewImage').src = e.target.result;
                                    document.getElementById('previewModal').classList.add('active');
                                    document.body.style.overflow = 'hidden'; // Evitar scroll
                                }

                                reader.readAsDataURL(this.files[0]);
                            }
                        });
                    }
                });

                // Función para previsualizar la foto antes de subir
                document.getElementById('inputFotoPerfil').addEventListener('change', function (e) {
                    if (this.files && this.files[0]) {
                        imagenTemporal = this.files[0]; // Guardar archivo temporalmente
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            mostrarPreview(e.target.result);
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });

                // Función para confirmar la foto
                function confirmarFoto() {
                    const previewModal = document.getElementById('previewModal');

                    // Cambiar a estado de carga
                    previewModal.querySelector('.preview-content').innerHTML = `
                <h3 class="preview-title">Subiendo foto</h3>
                <div class="loading-spinner"></div>
                <p>Por favor espera...</p>
            `;

                    // Crear un FormData y enviar el archivo
                    const formData = new FormData();
                    formData.append('foto_perfil', imagenTemporal);
                    formData.append('pestaña', 'datos-personales');

                    // Enviar con Fetch API
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.text();
                        })
                        .then(() => {
                            // Mostrar animación de éxito
                            previewModal.querySelector('.preview-content').innerHTML = `
                    <h3 class="preview-title">¡Éxito!</h3>
                    <div class="success-check">
                        <i class="fas fa-check"></i>
                    </div>
                    <p>Foto actualizada correctamente</p>
                    <p>La página se recargará automáticamente</p>
                `;

                            // Recargar después de 2 segundos
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            // Mostrar mensaje de error
                            previewModal.querySelector('.preview-content').innerHTML = `
                    <h3 class="preview-title">Error</h3>
                    <div class="error-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <p>Ocurrió un error al subir la foto</p>
                    <div class="preview-buttons">
                        <button class="btn-cancel" onclick="cancelarPreview()">Cerrar</button>
                    </div>
                `;
                        });
                }

                // Función para cancelar la previsualización
                function cancelarPreview() {
                    const previewModal = document.getElementById('previewModal');
                    previewModal.classList.remove('active');
                    document.body.style.overflow = ''; // Restaurar scroll

                    // Limpiar el input de archivo
                    document.getElementById('inputFotoPerfil').value = '';
                    imagenTemporal = null;
                }

                // Cerrar modal al hacer clic fuera del contenido
                document.getElementById('previewModal').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cancelarPreview();
                    }
                });

                // Cerrar modal con la tecla Escape
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        cancelarPreview();
                    }
                });

                // Tooltip para la foto de perfil
                document.querySelector('.foto-perfil').addEventListener('mouseenter', function () {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = 'Haz clic para cambiar la foto';
                    tooltip.style.position = 'absolute';
                    tooltip.style.bottom = '100%';
                    tooltip.style.left = '50%';
                    tooltip.style.transform = 'translateX(-50%)';
                    tooltip.style.background = '#333';
                    tooltip.style.color = 'white';
                    tooltip.style.padding = '5px 10px';
                    tooltip.style.borderRadius = '4px';
                    tooltip.style.fontSize = '12px';
                    tooltip.style.whiteSpace = 'nowrap';
                    tooltip.style.marginBottom = '5px';
                    tooltip.style.zIndex = '1000';

                    this.appendChild(tooltip);

                    setTimeout(() => {
                        if (this.contains(tooltip)) {
                            this.removeChild(tooltip);
                        }
                    }, 2000);
                });

                document.querySelector('.foto-perfil').addEventListener('mouseleave', function () {
                    const tooltip = this.querySelector('.tooltip');
                    if (tooltip) {
                        this.removeChild(tooltip);
                    }
                });

                // Variables para controlar la validación
                let ultimoCodigoValidado = '';
                let codigoEsValido = true;

                // Validar código de contrato único con mejor UX
                function validarCodigoContrato(codigo) {
                    // Si está vacío o es el mismo que ya validamos, no hacer nada
                    if (!codigo || codigo === ultimoCodigoValidado) {
                        return;
                    }

                    // Si estamos editando un contrato existente, obtener su ID para excluirlo de la validación
                    const idContratoActual = '<?= $contratoActual ? $contratoActual["CodContrato"] : 0 ?>';

                    // Mostrar estado de carga
                    document.getElementById('codigo-contrato-error').style.display = 'none';
                    document.getElementById('codigo-contrato-success').style.display = 'none';

                    // Crear o mostrar indicador de carga
                    let loadingIndicator = document.getElementById('loading-indicator');
                    if (!loadingIndicator) {
                        loadingIndicator = document.createElement('div');
                        loadingIndicator.id = 'loading-indicator';
                        loadingIndicator.className = 'loading-indicator';
                        document.getElementById('codigo_manual_contrato').parentNode.appendChild(loadingIndicator);
                    }
                    loadingIndicator.style.display = 'inline-block';

                    fetch(`validar_codigo_contrato.php?codigo=${encodeURIComponent(codigo)}&excluir=${idContratoActual}`)
                        .then(response => response.json())
                        .then(data => {
                            // Ocultar indicador de carga
                            loadingIndicator.style.display = 'none';

                            ultimoCodigoValidado = codigo;

                            if (data.existe) {
                                // Código ya existe
                                document.getElementById('codigo-contrato-error').style.display = 'block';
                                document.getElementById('codigo-contrato-success').style.display = 'none';
                                codigoEsValido = false;

                                // Resaltar el campo en rojo
                                document.getElementById('codigo_manual_contrato').style.borderColor = '#dc3545';
                                document.getElementById('codigo_manual_contrato').style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                            } else {
                                // Código disponible
                                document.getElementById('codigo-contrato-error').style.display = 'none';
                                document.getElementById('codigo-contrato-success').style.display = 'block';
                                codigoEsValido = true;

                                // Quitar resaltado
                                document.getElementById('codigo_manual_contrato').style.borderColor = '';
                                document.getElementById('codigo_manual_contrato').style.boxShadow = '';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            loadingIndicator.style.display = 'none';
                            codigoEsValido = true; // En caso de error, permitir enviar el formulario
                        });
                }

                // También validar cuando el usuario escribe (pero con debounce para no hacer muchas peticiones)
                let timeoutId;
                document.getElementById('codigo_manual_contrato').addEventListener('input', function (e) {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        validarCodigoContrato(this.value);
                    }, 800); // Esperar 800ms después de que el usuario deje de escribir
                });

                // Validar antes de enviar el formulario
                document.querySelector('form').addEventListener('submit', function (e) {
                    if (!codigoEsValido) {
                        e.preventDefault();
                        alert('No puede guardar el contrato con un código que ya existe. Por favor, use un código único.');
                        document.getElementById('codigo_manual_contrato').focus();
                    }
                });

                // Funciones para el modal de movimientos
                function editarMovimiento(idMovimiento) {
                    // Hacer una solicitud AJAX para obtener los datos del movimiento
                    fetch(`obtener_movimiento.php?id=${idMovimiento}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.json();
                        })
                        .then(movimiento => {
                            document.getElementById('modalMovimiento').style.display = 'block';
                            document.getElementById('idMovimiento').value = idMovimiento;
                            document.getElementById('edit_cod_cargo').value = movimiento.CodNivelesCargos || '';
                            document.getElementById('edit_sucursal').value = movimiento.Sucursal || '';

                            // Formatear fecha (eliminar la parte de tiempo si existe)
                            const fechaInicio = movimiento.Fecha ? movimiento.Fecha.split(' ')[0] : '';
                            document.getElementById('edit_fecha_inicio').value = fechaInicio;

                            const fechaFin = movimiento.Fin ? movimiento.Fin.split(' ')[0] : '';
                            document.getElementById('edit_fecha_fin').value = fechaFin;

                            document.getElementById('edit_tipo_contrato').value = movimiento.CodTipoContrato || '';
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cargar los datos del movimiento');
                        });
                }

                function cerrarModalMovimiento() {
                    document.getElementById('modalMovimiento').style.display = 'none';
                }

                // Cerrar modal al hacer clic fuera
                document.getElementById('modalMovimiento').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalMovimiento();
                    }
                });

                function editarSalarioINSS(idSalarioINSS) {
                    fetch(`obtener_salario_inss.php?id=${idSalarioINSS}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.json();
                        })
                        .then(salario => {
                            // Llenar el formulario modal con los datos
                            document.getElementById('monto_salario_inss_modal').value = salario.monto_salario_inss || '';
                            document.getElementById('inicio_inss_modal').value = salario.inicio || '';
                            document.getElementById('observaciones_inss_modal').value = salario.observaciones_inss || '';

                            // Mostrar el modal de edición
                            document.getElementById('modalSalarioINSS').style.display = 'block';

                            // Cambiar el formulario para modo edición
                            const form = document.getElementById('formSalarioINSS');
                            // Crear campo hidden para el ID si no existe
                            if (!form.querySelector('input[name="id_salario_inss"]')) {
                                const hiddenInput = document.createElement('input');
                                hiddenInput.type = 'hidden';
                                hiddenInput.name = 'id_salario_inss';
                                hiddenInput.value = idSalarioINSS;
                                form.appendChild(hiddenInput);
                            } else {
                                form.querySelector('input[name="id_salario_inss"]').value = idSalarioINSS;
                            }

                            // Cambiar la acción a editar
                            const accionInput = form.querySelector('input[name="accion_inss"]');
                            if (accionInput) {
                                accionInput.value = 'editar';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cargar los datos del salario INSS');
                        });
                }

                // Función para editar categoría
                function editarCategoria(idCategoria) {
                    // Mostrar indicador de carga
                    const modal = document.getElementById('modalCategoria');
                    modal.querySelector('.modal-content').innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div class="loading-spinner"></div>
                    <p>Cargando datos de la categoría...</p>
                </div>
            `;
                    modal.style.display = 'block';

                    // Hacer una solicitud AJAX para obtener los datos de la categoría
                    fetch(`obtener_categoria.php?id=${idCategoria}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(categoria => {
                            // Restaurar el formulario
                            modal.querySelector('.modal-content').innerHTML = `
                        <h3 style="color: #0E544C; margin-bottom: 20px;">Editar Categoría</h3>
                        <form method="POST" action="" id="formCategoria">
                            <input type="hidden" name="accion_categoria" value="editar">
                            <input type="hidden" name="id_categoria_edit" id="idCategoriaEdit" value="">
                            <input type="hidden" name="pestaña" value="categoria">
                            
                            <div class="form-group">
                                <label for="edit_id_categoria">Categoría *</label>
                                <select id="edit_id_categoria" name="id_categoria" class="form-control" required>
                                    <option value="">Seleccionar categoría...</option>
                                    <?php foreach ($todasCategorias as $categoria): ?>
                                        <option value="<?= $categoria['idCategoria'] ?>">
                                            <?= htmlspecialchars($categoria['NombreCategoria']) ?> 
                                            (Peso: <?= $categoria['Peso'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_fecha_inicio">Fecha de Inicio *</label>
                                <input type="date" id="edit_fecha_inicio" name="fecha_inicio" class="form-control" required>
                            </div>
                            
                            <div style="display:none;" class="form-group">
                                <label for="edit_fecha_fin">Fecha de Fin</label>
                                <input type="date" id="edit_fecha_fin" name="fecha_fin" class="form-control">
                                <small style="color: #6c757d;">Dejar vacío si es la categoría actual</small>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                                <button type="button" class="btn-submit" onclick="cerrarModalCategoria()" style="background-color: #6c757d;">Cancelar</button>
                                <button type="submit" class="btn-submit">Guardar Cambios</button>
                            </div>
                        </form>
                    `;

                            // Llenar el formulario con los datos
                            document.getElementById('idCategoriaEdit').value = idCategoria;
                            document.getElementById('edit_id_categoria').value = categoria.idCategoria || '';
                            document.getElementById('edit_fecha_inicio').value = categoria.FechaInicio || '';
                            document.getElementById('edit_fecha_fin').value = categoria.FechaFin || '';

                            // Reasignar el event listener al modal
                            document.getElementById('modalCategoria').addEventListener('click', function (e) {
                                if (e.target === this) {
                                    cerrarModalCategoria();
                                }
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            modal.querySelector('.modal-content').innerHTML = `
                        <h3 style="color: #dc3545; margin-bottom: 20px;">Error</h3>
                        <p>Error al cargar los datos de la categoría: ${error.message}</p>
                        <div style="text-align: center; margin-top: 20px;">
                            <button type="button" class="btn-submit" onclick="cerrarModalCategoria()">Cerrar</button>
                        </div>
                    `;
                        });
                }

                // Función para cerrar el modal de categoría
                function cerrarModalCategoria() {
                    document.getElementById('modalCategoria').style.display = 'none';
                }

                // Cerrar modal al hacer clic fuera
                document.getElementById('modalCategoria').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalCategoria();
                    }
                });

                // Función para actualizar campos según el tipo de adendum
                function actualizarCamposAdendum() {
                    const tipoAdendum = document.getElementById('tipo_adendum').value;
                    const codCargo = document.getElementById('cod_cargo_adendum').value;

                    // Grupos de campos
                    const grupoCargo = document.getElementById('grupo_cargo');
                    const grupoSucursal = document.getElementById('grupo_sucursal');
                    const grupoCategoria = document.getElementById('grupo_categoria');
                    const grupoSalario = document.getElementById('grupo_salario');

                    // Campos individuales
                    const cargoInput = document.getElementById('cod_cargo_adendum');
                    const sucursalInput = document.getElementById('sucursal_adendum');
                    const categoriaInput = document.getElementById('id_categoria_adendum');
                    const salarioInput = document.getElementById('salario_adendum');

                    // Resetear requeridos
                    cargoInput.required = false;
                    sucursalInput.required = false;
                    categoriaInput.required = false;
                    salarioInput.required = false;

                    // MOSTRAR/OCULTAR CATEGORÍA SEGÚN CÓDIGO DE CARGO
                    if (codCargo === '2' || codCargo === '5') {
                        // Mostrar categoría solo para códigos 2 y 5
                        grupoCategoria.style.display = 'block';
                        categoriaInput.required = true;
                    } else {
                        grupoCategoria.style.display = 'none';
                        categoriaInput.required = false;
                    }

                    switch (tipoAdendum) {
                        case 'cargo':
                            grupoCargo.style.display = 'block';
                            grupoSucursal.style.display = 'block';
                            // La categoría ya se maneja según el código de cargo
                            grupoSalario.style.display = 'none';

                            cargoInput.required = true;
                            sucursalInput.required = true;
                            break;

                        case 'salario':
                            grupoCargo.style.display = 'none';
                            grupoSucursal.style.display = 'none';
                            grupoCategoria.style.display = 'none'; // Ocultar categoría en ajuste salarial
                            grupoSalario.style.display = 'block';

                            salarioInput.required = true;
                            break;

                        case 'ambos':
                            grupoCargo.style.display = 'block';
                            grupoSucursal.style.display = 'block';
                            // La categoría ya se maneja según el código de cargo
                            grupoSalario.style.display = 'block';

                            cargoInput.required = true;
                            sucursalInput.required = true;
                            salarioInput.required = true;
                            break;

                        default:
                            grupoCargo.style.display = 'none';
                            grupoSucursal.style.display = 'none';
                            grupoCategoria.style.display = 'none';
                            grupoSalario.style.display = 'none';
                    }
                }

                // Función para editar adendum
                function editarAdendum(idAdendum) {
                    // Hacer una solicitud AJAX para obtener los datos del adendum
                    fetch(`obtener_adendum.php?id=${idAdendum}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.json();
                        })
                        .then(adendum => {
                            // Abrir modal de edición (similar al de categorías)
                            document.getElementById('modalAdendum').style.display = 'block';

                            // Llenar el formulario con los datos
                            document.getElementById('edit_id_adendum').value = adendum.CodAsignacionNivelesCargos;
                            document.getElementById('edit_tipo_adendum').value = adendum.TipoAdendum || '';
                            document.getElementById('edit_cod_cargo_adendum').value = adendum.CodNivelesCargos || '';
                            document.getElementById('edit_sucursal_adendum').value = adendum.Sucursal || '';
                            //document.getElementById('edit_id_categoria_adendum').value = adendum.idCategoria || '';
                            document.getElementById('edit_salario_adendum').value = adendum.Salario || '';
                            document.getElementById('edit_fecha_inicio_adendum').value = adendum.Fecha || '';
                            document.getElementById('edit_fecha_fin_adendum').value = adendum.Fin || '';
                            document.getElementById('edit_observaciones_adendum').value = adendum.Observaciones || '';

                            // Actualizar campos según el tipo
                            actualizarCamposEdicionAdendum();
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cargar los datos del adendum');
                        });
                }

                // Modal para editar adendum (agregar al HTML)
                function cerrarModalAdendum() {
                    document.getElementById('modalAdendum').style.display = 'none';
                }

                // Función para establecer fecha actual en todos los modales
                function establecerFechasActuales() {
                    const fechaActual = new Date().toISOString().split('T')[0];

                    // Campos de fecha que deben tener fecha actual por defecto
                    const camposFecha = [
                        'desde_modal', 'inicio_modal', 'inicio_inss_modal',
                        'nuevo_fecha_inicio', 'fecha_inicio', 'fecha_inicio_adendum',
                        'edit_fecha_inicio', 'edit_fecha_inicio_adendum'
                    ];

                    camposFecha.forEach(id => {
                        const campo = document.getElementById(id);
                        if (campo && !campo.value) {
                            campo.value = fechaActual;
                        }
                    });
                }

                // Ejecutar cuando se abra cualquier modal
                document.addEventListener('DOMContentLoaded', function () {
                    // Observar cambios en los modales
                    const observer = new MutationObserver(function (mutations) {
                        mutations.forEach(function (mutation) {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                                const modal = mutation.target;
                                if (modal.style.display === 'block') {
                                    establecerFechasActuales();
                                }
                            }
                        });
                    });

                    // Observar todos los modales
                    const modales = document.querySelectorAll('.modal-backdrop');
                    modales.forEach(modal => {
                        observer.observe(modal, { attributes: true });
                    });
                });

                // Deshabilitar funcionalidades si no hay contrato activo
                function verificarContratoActivo() {
                    const tieneContrato = <?= tieneContratoActivo($codOperario) ? 'true' : 'false' ?>;
                    const pestañasRequierenContrato = ['inss', 'adendums', 'salario', 'categoria', 'movimientos'];
                    const pestañaActual = '<?= $pestaña_activa ?>';

                    if (!tieneContrato && pestañasRequierenContrato.includes(pestañaActual)) {
                        // Deshabilitar botones de agregar
                        const botonesAgregar = document.querySelectorAll('button[onclick*="abrirModal"], .btn-submit');
                        botonesAgregar.forEach(boton => {
                            if (!boton.onclick || !boton.onclick.toString().includes('Terminacion')) {
                                boton.disabled = true;
                                boton.title = 'Requiere contrato activo';
                                boton.style.opacity = '0.6';
                                boton.style.cursor = 'not-allowed';
                            }
                        });

                        // Deshabilitar formularios
                        const formularios = document.querySelectorAll('form');
                        formularios.forEach(form => {
                            if (!form.id.includes('Terminacion')) {
                                form.addEventListener('submit', function (e) {
                                    e.preventDefault();
                                    alert('No se puede realizar esta acción porque el colaborador no tiene un contrato activo.');
                                });
                            }
                        });
                    }
                }

                // Ejecutar al cargar la página
                document.addEventListener('DOMContentLoaded', verificarContratoActivo);

                // Función para mostrar categoría sugerida según el cargo
                //function mostrarCategoriaSugerida() {
                //    const codCargo = document.getElementById('cod_cargo').value;
                //    const grupoCategoria = document.getElementById('grupo_categoria_contrato');
                //    
                //    if (!grupoCategoria) {
                //        // Crear el elemento si no existe
                //        const afterSucursal = document.getElementById('sucursal').parentNode;
                //        const nuevoGrupo = document.createElement('div');
                //        nuevoGrupo.id = 'grupo_categoria_contrato';
                //        nuevoGrupo.className = 'form-group';
                //        nuevoGrupo.innerHTML = `
                //            <label for="id_categoria_contrato">Categoría Sugerida</label>
                //            <select id="id_categoria_contrato" name="id_categoria" class="form-control">
                //                <option value="">Seleccionar categoría...</option>
                //                <?php foreach ($todasCategorias as $categoria): ?>
                //                    <option value="<?= $categoria['idCategoria'] ?>">
                //                        <?= htmlspecialchars($categoria['NombreCategoria']) ?> (Peso: <?= $categoria['Peso'] ?>)
                //                    </option>
                //                <?php endforeach; ?>
                //            </select>
                //            <small style="color: #6c757d;" id="textoCategoriaSugerida"></small>
                //        `;
                //        afterSucursal.parentNode.insertBefore(nuevoGrupo, afterSucursal.nextSibling);
                //    }
                //    
                // Mapeo de cargos a categorías sugeridas
                //    const categoriasSugeridas = {
                //        '2': '5',  // Operario -> Categoría 5
                //        '5': '1',  // Líder de Sucursal -> Categoría 1
                //        // Agregar más mapeos según necesites
                //    };
                //    
                //    const categoriaSugerida = categoriasSugeridas[codCargo];
                //    const selectCategoria = document.getElementById('id_categoria_contrato');
                //    const textoCategoria = document.getElementById('textoCategoriaSugerida');
                //    
                //    if (categoriaSugerida && selectCategoria) {
                //        for (let i = 0; i < selectCategoria.options.length; i++) {
                //            if (selectCategoria.options[i].value == categoriaSugerida) {
                //                selectCategoria.value = categoriaSugerida;
                //                textoCategoria.textContent = 'Categoría sugerida para este cargo';
                //                break;
                //            }
                //        }
                //    } else {
                //        textoCategoria.textContent = 'Seleccione una categoría apropiada para el cargo';
                //    }
                //}

                // Llamar la función cuando cambie el cargo
                //document.getElementById('cod_cargo').addEventListener('change', mostrarCategoriaSugerida);

                // También llamar al cargar la página si ya hay un cargo seleccionado
                //document.addEventListener('DOMContentLoaded', function() {
                //    if (document.getElementById('cod_cargo').value) {
                //        mostrarCategoriaSugerida();
                //    }
                //});

                // Agregar evento al cambiar el cargo en adendums
                document.addEventListener('DOMContentLoaded', function () {
                    const cargoSelectAdendum = document.getElementById('cod_cargo_adendum');
                    if (cargoSelectAdendum) {
                        cargoSelectAdendum.addEventListener('change', function () {
                            actualizarCamposAdendum();
                        });
                    }

                    // También para la edición
                    const cargoSelectEditAdendum = document.getElementById('edit_cod_cargo_adendum');
                    if (cargoSelectEditAdendum) {
                        cargoSelectEditAdendum.addEventListener('change', function () {
                            actualizarCamposEdicionAdendum();
                        });
                    }
                });

                // Función para actualizar el comportamiento del campo fecha fin
                function actualizarComportamientoFechaFin() {
                    const fechaFinInput = document.getElementById('fecha_fin_adendum');
                    const ayudaFechaFin = document.createElement('small');
                    ayudaFechaFin.style.color = '#6c757d';
                    ayudaFechaFin.style.display = 'block';
                    fechaFinInput.parentNode.appendChild(ayudaFechaFin);

                    // Verificar si hay adendums existentes
                    const tieneAdendums = <?= count($adendumsColaborador) > 0 ? 'true' : 'false' ?>;

                    if (tieneAdendums) {
                        ayudaFechaFin.textContent = 'Puede crear múltiples adendas activas simultáneamente. Para finalizar una adenda, use el botón de finalización en el historial.';
                        fechaFinInput.placeholder = 'Opcional - para adendum con fecha específica';
                    } else {
                        ayudaFechaFin.textContent = 'Para el primer adendum, puede especificar una fecha fin o dejar vacío para adendum indefinido.';
                        fechaFinInput.placeholder = 'Opcional';
                    }
                }

                // Llamar al cargar la página
                document.addEventListener('DOMContentLoaded', function () {
                    actualizarComportamientoFechaFin();
                });

                // Función para abrir modal de liquidación
                function abrirModalLiquidacion(idContrato, fechaLiquidacionActual = '') {
                    document.getElementById('modalLiquidacion').style.display = 'block';
                    document.getElementById('idContratoLiquidacion').value = idContrato;

                    // Si hay una fecha actual, establecerla
                    if (fechaLiquidacionActual) {
                        document.getElementById('fecha_liquidacion_modal').value = fechaLiquidacionActual;
                    } else {
                        // Establecer fecha actual por defecto
                        document.getElementById('fecha_liquidacion_modal').valueAsDate = new Date();
                    }
                }

                // Función para cerrar modal de liquidación
                function cerrarModalLiquidacion() {
                    document.getElementById('modalLiquidacion').style.display = 'none';
                }

                // Cerrar modal al hacer clic fuera
                document.getElementById('modalLiquidacion').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalLiquidacion();
                    }
                });

                // Validación del formulario de liquidación
                document.getElementById('formLiquidacion').addEventListener('submit', function (e) {
                    const fechaLiquidacion = document.getElementById('fecha_liquidacion_modal').value;

                    if (!fechaLiquidacion) {
                        e.preventDefault();
                        alert('Por favor seleccione una fecha de liquidación.');
                        return;
                    }

                    if (!confirm('¿Está seguro de que desea asignar esta fecha de liquidación?')) {
                        e.preventDefault();
                    }
                });

                // Funciones para el modal de finalizar adenda
                function abrirModalFinalizarAdenda(idAdendum) {
                    document.getElementById('modalFinalizarAdenda').style.display = 'block';
                    document.getElementById('idAdendumFinalizar').value = idAdendum;
                    document.getElementById('fecha_fin_adenda').valueAsDate = new Date();
                }

                function cerrarModalFinalizarAdenda() {
                    document.getElementById('modalFinalizarAdenda').style.display = 'none';
                }

                // Cerrar modal al hacer clic fuera
                document.getElementById('modalFinalizarAdenda').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalFinalizarAdenda();
                    }
                });

                // Función para editar terminación de contrato del historial
                function abrirModalEditarTerminacion(idContrato) {
                    // Cargar datos del contrato vía AJAX
                    fetch(`obtener_contrato_terminacion.php?id=${idContrato}`)
                        .then(response => response.json())
                        .then(contrato => {
                            document.getElementById('modalTerminacion').style.display = 'block';
                            document.querySelector('#modalTerminacion h3').textContent = 'Editar Información de Terminación';

                            // Cambiar el formulario a modo edición
                            const form = document.getElementById('formTerminacion');

                            // CAMBIAR LA ACCIÓN A EDITAR_TERMINACION
                            const inputAccion = form.querySelector('input[name="accion_contrato"]');
                            inputAccion.value = 'editar_terminacion';

                            // Agregar campo hidden para ID si no existe
                            let inputIdEditar = form.querySelector('input[name="id_contrato_editar"]');
                            if (!inputIdEditar) {
                                inputIdEditar = document.createElement('input');
                                inputIdEditar.type = 'hidden';
                                inputIdEditar.name = 'id_contrato_editar';
                                form.appendChild(inputIdEditar);
                            }
                            inputIdEditar.value = idContrato;

                            // Ocultar el campo id_contrato original para no causar conflictos
                            const inputIdOriginal = form.querySelector('input[name="id_contrato"]');
                            if (inputIdOriginal) {
                                inputIdOriginal.disabled = true;
                            }

                            // Llenar los campos con los datos del contrato
                            document.getElementById('fecha_fin_contrato').value = contrato.fin_contrato || '';
                            document.getElementById('fecha_terminacion').value = contrato.fecha_salida || '';
                            document.getElementById('fecha_liquidacion').value = contrato.fecha_liquidacion || '';
                            document.getElementById('tipo_salida').value = contrato.cod_tipo_salida || '';
                            document.getElementById('motivo_salida').value = contrato.motivo || '';
                            document.getElementById('dias_trabajados').value = contrato.dias_trabajados || '';
                            document.getElementById('monto_indemnizacion').value = contrato.monto_indemnizacion || '';
                            document.getElementById('devolucion_herramientas').value = contrato.devolucion_herramientas_trabajo || '0';
                            document.getElementById('persona_recibe_herramientas').value = contrato.persona_recibe_herramientas_trabajo || '';

                            // Mostrar campo de persona si aplica devolución
                            if (contrato.devolucion_herramientas_trabajo == '1') {
                                document.getElementById('grupoPersonaHerramientas').style.display = 'block';
                            }

                            // Cambiar el texto del botón
                            const btnSubmit = form.querySelector('button[type="submit"]');
                            btnSubmit.textContent = 'Guardar Cambios';
                            btnSubmit.style.backgroundColor = '#0E544C'; // Color verde en lugar de rojo
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error al cargar los datos del contrato: ' + error.message);
                        });
                }
            </script>

            <!-- Modal para ver foto de perfil en tamaño completo -->
            <div id="modalVerFoto"
                style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); overflow: auto;">
                <span onclick="cerrarModalVerFoto()"
                    style="position: absolute; top: 20px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer; z-index: 10000;">&times;</span>
                <img id="imagenFotoCompleta"
                    style="margin: auto; display: block; max-width: 90%; max-height: 90%; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
            </div>

            <script>
                // Función para abrir modal de ver foto
                function abrirModalVerFoto(rutaFoto) {
                    document.getElementById('modalVerFoto').style.display = 'block';
                    document.getElementById('imagenFotoCompleta').src = rutaFoto;
                }

                // Función para cerrar modal de ver foto
                function cerrarModalVerFoto() {
                    document.getElementById('modalVerFoto').style.display = 'none';
                }

                // Cerrar modal al hacer clic fuera de la imagen
                document.getElementById('modalVerFoto').addEventListener('click', function (e) {
                    if (e.target === this) {
                        cerrarModalVerFoto();
                    }
                });

                // Mostrar ícono de ver al hacer hover sobre la foto
                document.addEventListener('DOMContentLoaded', function () {
                    const fotoContainer = document.querySelector('.foto-perfil');
                    const viewIcon = document.querySelector('.view-icon');

                    if (fotoContainer && viewIcon) {
                        fotoContainer.addEventListener('mouseenter', function () {
                            viewIcon.style.opacity = '1';
                        });

                        fotoContainer.addEventListener('mouseleave', function () {
                            viewIcon.style.opacity = '0';
                        });
                    }
                });
            </script>
        </div>
    </div>
</body>

</html>