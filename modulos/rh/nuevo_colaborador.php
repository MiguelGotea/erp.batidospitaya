<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';

//******************************Estándar para header******************************

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
// Verificar acceso al módulo
if (!tienePermiso('nuevo_colaborador', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Obtener el ID del usuario actual para guardar quién registra
$usuarioActualId = $_SESSION['usuario_id'];
//******************************Estándar para header, termina******************************

// Inicializar variables
$errores = [];
$exito = false;
$valores = [
    'Nombre'                 => '',
    'Nombre2'                => '',
    'Apellido'               => '',
    'Apellido2'              => '',
    'Cedula'                 => '',
    'Celular'                => '',
    'usuario'                => '',
    'clave'                  => '',
    // Contrato (obligatorios)
    'codigo_manual_contrato' => '',
    'cod_tipo_contrato'      => '',
    'cod_cargo'              => '',
    'sucursal'               => '',
    'ciudad'                 => '',
    'inicio_contrato'        => date('Y-m-d'),
    'fin_contrato'           => '',
    'salario_inicial'        => '',
];

// Obtener el último código de operario para predecir el siguiente
$siguienteCodigo = 1;
$stmt = $conn->query("SELECT MAX(CodOperario) as ultimo_codigo FROM Operarios");
if ($stmt) {
    $result = $stmt->fetch();
    $siguienteCodigo = $result['ultimo_codigo'] + 1;
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Datos del colaborador ────────────────────────────────────────────────
    $valores['Nombre']   = trim($_POST['Nombre'] ?? '');
    $valores['Nombre2']  = trim($_POST['Nombre2'] ?? '');
    $valores['Apellido'] = trim($_POST['Apellido'] ?? '');
    $valores['Apellido2']= trim($_POST['Apellido2'] ?? '');
    $valores['Cedula']   = trim($_POST['Cedula'] ?? '');
    $valores['Celular']  = trim($_POST['Celular'] ?? '');
    $valores['usuario']  = trim($_POST['usuario'] ?? '');
    $valores['clave']    = trim($_POST['clave'] ?? '');

    // ── Datos del contrato (obligatorios) ────────────────────────────────────
    $valores['codigo_manual_contrato'] = trim($_POST['codigo_manual_contrato'] ?? '');
    $valores['cod_tipo_contrato']      = trim($_POST['cod_tipo_contrato'] ?? '');
    $valores['cod_cargo']              = trim($_POST['cod_cargo'] ?? '');
    $valores['sucursal']               = trim($_POST['sucursal'] ?? '');
    $valores['ciudad']                 = trim($_POST['ciudad'] ?? '');
    $valores['inicio_contrato']        = trim($_POST['inicio_contrato'] ?? date('Y-m-d'));
    $valores['fin_contrato']           = trim($_POST['fin_contrato'] ?? '');
    $valores['salario_inicial']        = trim($_POST['salario_inicial'] ?? '');

    // ── Validaciones colaborador ─────────────────────────────────────────────
    if (empty($valores['Nombre']))    $errores[] = 'El primer nombre es obligatorio';
    if (empty($valores['Apellido']))  $errores[] = 'El primer apellido es obligatorio';
    if (empty($valores['Cedula']))    $errores[] = 'La cédula es obligatoria';

    // ── Validaciones contrato (campos obligatorios) ──────────────────────────
    if (empty($valores['cod_tipo_contrato'])) $errores[] = 'El tipo de contrato es obligatorio';
    if (empty($valores['cod_cargo']))          $errores[] = 'El cargo es obligatorio';
    if (empty($valores['sucursal']))           $errores[] = 'La sucursal / área es obligatoria';
    if (empty($valores['inicio_contrato']))    $errores[] = 'La fecha de inicio del contrato es obligatoria';

    // ── Verificar cédula duplicada ───────────────────────────────────────────
    if (empty($errores)) {
        $cedulaLimpia = str_replace('-', '', $valores['Cedula']);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Operarios WHERE REPLACE(Cedula, '-', '') = ?");
        $stmt->execute([$cedulaLimpia]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'Ya existe un colaborador con esta cédula';
        }
    }

    // ── Verificar usuario duplicado (si se ingresó manualmente) ─────────────
    if (empty($errores) && !empty($valores['usuario'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Operarios WHERE usuario = ?");
        $stmt->execute([$valores['usuario']]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'Ya existe un colaborador con este usuario';
        }
    }

    // ── Verificar código de contrato único (si se ingresó) ──────────────────
    if (empty($errores) && !empty($valores['codigo_manual_contrato'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Contratos WHERE codigo_manual_contrato = ?");
        $stmt->execute([$valores['codigo_manual_contrato']]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = 'El código de contrato ya existe. Debe ser único.';
        }
    }

    // ── Guardar si todo está correcto ────────────────────────────────────────
    if (empty($errores)) {
        try {
            $conn->beginTransaction();

            // Generar usuario/clave si no se ingresaron manualmente
            $usuarioGenerado = empty($valores['usuario'])
                ? generarUsuario($valores['Nombre'], $valores['Apellido'], $valores['Cedula'])
                : $valores['usuario'];

            $claveGenerada = empty($valores['clave'])
                ? generarClave($valores['Nombre'], $valores['Apellido'])
                : $valores['clave'];

            // 1. Insertar en Operarios
            $stmt = $conn->prepare("
                INSERT INTO Operarios
                    (Nombre, Nombre2, Apellido, Apellido2, Cedula, Celular,
                     usuario, clave, Operativo, FechaCreacion, registrado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)
            ");
            $stmt->execute([
                $valores['Nombre'],
                $valores['Nombre2'],
                $valores['Apellido'],
                $valores['Apellido2'],
                $valores['Cedula'],
                $valores['Celular'],
                $usuarioGenerado,
                $claveGenerada,
                $usuarioActualId
            ]);
            $nuevoId = $conn->lastInsertId();

            // 2. Insertar en AsignacionNivelesCargos (registro base/inicial del contrato)
            //    TipoAdendum = 'inicial' identifica este registro como el primero del contrato.
            //    codigo_contrato_asociado = código manual ingresado en el form.
            //    El CodContrato (FK numérico) se actualiza vía UPDATE tras crear el contrato.
            $salarioInicial        = null; // Se registra en editar_colaborador
            $codigoContratoForm    = !empty($valores['codigo_manual_contrato']) ? $valores['codigo_manual_contrato'] : null;

            $stmtAsig = $conn->prepare("
                INSERT INTO AsignacionNivelesCargos
                    (CodOperario, CodNivelesCargos, Fecha, Sucursal, CodTipoContrato,
                     TipoAdendum, Salario, codigo_contrato_asociado, cod_usuario_creador)
                VALUES (?, ?, ?, ?, ?, 'inicial', ?, ?, ?)
            ");
            $stmtAsig->execute([
                $nuevoId,
                $valores['cod_cargo'],
                $valores['inicio_contrato'],
                $valores['sucursal'],
                $valores['cod_tipo_contrato'],
                $salarioInicial,
                $codigoContratoForm,
                $usuarioActualId
            ]);
            $codAsignacion = $conn->lastInsertId();

            // 3. Insertar en Contratos
            //    fin_contrato solo aplica para tipo Determinado (CodTipoContrato = 1).
            //    ciudad se obtiene del departamento de la sucursal seleccionada (no del form).
            $finContrato = ($valores['cod_tipo_contrato'] == 1 && !empty($valores['fin_contrato']))
                ? $valores['fin_contrato']
                : null;

            // Buscar la ciudad (departamento) de la sucursal seleccionada
            $ciudadSucursal = obtenerDepartamentoSucursal($valores['sucursal']);

            $stmtCont = $conn->prepare("
                INSERT INTO Contratos
                    (cod_tipo_contrato, codigo_manual_contrato, cod_operario,
                     inicio_contrato, fin_contrato, ciudad, cod_sucursal_contrato,
                     monto_contrato, salario_inicial, frecuencia_pago,
                     CodAsignacionNivelesCargos, cod_usuario_creador)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'quincenal', ?, ?)
            ");
            $stmtCont->execute([
                $valores['cod_tipo_contrato'],
                $codigoContratoForm,
                $nuevoId,
                $valores['inicio_contrato'],
                $finContrato,
                $ciudadSucursal !== 'Desconocido' ? $ciudadSucursal : null,  // ciudad del departamento
                $valores['sucursal'],
                $salarioInicial,   // monto_contrato
                $salarioInicial,   // salario_inicial
                $codAsignacion,
                $usuarioActualId
            ]);
            $codContrato = $conn->lastInsertId();

            // 4. Actualizar ANC con el CodContrato (FK numérico) ya creado
            $stmtUpdAsig = $conn->prepare("
                UPDATE AsignacionNivelesCargos
                SET CodContrato = ?
                WHERE CodAsignacionNivelesCargos = ?
            ");
            $stmtUpdAsig->execute([$codContrato, $codAsignacion]);

            $conn->commit();

            $_SESSION['exito'] = "Colaborador registrado exitosamente. Código: $nuevoId, Usuario: $usuarioGenerado, Clave: $claveGenerada";
            header("Location: editar_colaborador.php?id=$nuevoId");
            exit();

        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $errores[] = 'Error al registrar el colaborador: ' . $e->getMessage();
        }
    }
}

/**
 * Devuelve todos los cargos disponibles para el formulario de nuevo colaborador.
 * Función local para no depender de funciones_colaborador.php
 */
function ncGetCargos()
{
    global $conn;
    $stmt = $conn->prepare("SELECT CodNivelesCargos, Nombre FROM NivelesCargos WHERE CodNivelesCargos != 2 ORDER BY Nombre");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Devuelve todos los tipos de contrato disponibles para el formulario.
 * Función local para no depender de funciones_colaborador.php
 */
function ncGetTiposContrato()
{
    global $conn;
    $stmt = $conn->prepare("SELECT CodTipoContrato, nombre FROM TipoContrato WHERE CodTipoContrato != 3 ORDER BY nombre");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Genera el usuario automáticamente según las reglas especificadas
 * Ahora funciona con cualquier combinación de campos
 */
function generarUsuario($nombre, $apellido, $cedula)
{
    // Primero eliminar guiones si existen (por si acaso)
    $cedulaLimpia = str_replace('-', '', $cedula);

    // Obtener primeras dos letras del nombre (en minúsculas) o 'xx' si no hay
    $inicialNombre = 'xx';
    if (!empty($nombre)) {
        $inicialNombre = strtolower(substr($nombre, 0, 2));
    }

    // Obtener primeras dos letras del apellido (en minúsculas) o 'xx' si no hay
    $inicialApellido = 'xx';
    if (!empty($apellido)) {
        $inicialApellido = strtolower(substr($apellido, 0, 2));
    }

    // Obtener últimos 3 dígitos de la cédula (solo números) o '000' si no hay
    $digitosCedula = preg_replace('/[^0-9]/', '', $cedulaLimpia);
    $ultimosDigitos = '000';
    if (!empty($digitosCedula)) {
        if (strlen($digitosCedula) >= 3) {
            $ultimosDigitos = substr($digitosCedula, -3);
        } else {
            $ultimosDigitos = str_pad($digitosCedula, 3, '0', STR_PAD_LEFT);
        }
    }

    // Obtener la última letra de la cédula (si existe)
    $letraCedula = '';
    if (preg_match('/[A-Za-z]$/', $cedulaLimpia)) {
        $letraCedula = strtolower(substr($cedulaLimpia, -1));
    }

    return $inicialNombre . $inicialApellido . $ultimosDigitos . $letraCedula;
}

/**
 * Genera la clave automáticamente según las reglas especificadas
 */
function generarClave($nombre, $apellido)
{
    // Obtener primeras dos letras del nombre (en mayúsculas)
    $inicialNombre = strtolower(substr($nombre, 0, 2));

    // Obtener primeras two letras del apellido (en mayúsculas)
    $inicialApellido = strtolower(substr($apellido, 0, 2));

    // Obtener fecha actual en formato ddmmyy
    $fecha = date('dmy');

    return $inicialNombre . $inicialApellido . $fecha;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Colaborador</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }

        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #0E544C;
        }

        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #51B8AC;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .info-box p {
            margin-bottom: 8px;
        }

        .preview-box {
            background-color: #e8f4f3;
            border: 2px solid #51B8AC;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .preview-item {
            margin-bottom: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .preview-label {
            font-weight: bold;
            color: #0E544C;
            margin-right: 10px;
        }

        .preview-value {
            font-family: monospace;
            font-size: 16px;
            color: #333;
        }

        .registrado-por {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #51B8AC;
            font-style: italic;
            color: #666;
        }

        .btn-submit {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            width: 100%;
            transition: background-color 0.3s;
        }

        .btn-submit:hover {
            background-color: #0E544C;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert ul {
            margin-left: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .form-col {
                width: 100%;
            }

            .preview-value {
                font-size: 14px;
            }
        }

        /* ── Sección contrato ────────────────────────────────── */
        .seccion-contrato {
            margin-top: 28px;
            padding: 0;
            border-top: 2px solid #ddd;
        }

        .seccion-titulo {
            color: #0E544C;
            font-size: 1rem !important;
            font-weight: bold;
            margin: 20px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .seccion-titulo small {
            font-size: 0.78rem !important;
            color: #888;
            font-weight: normal;
        }

        /* Igualar estilos de select con los input[type=text] existentes */
        .form-group select,
        .form-group input[type="date"],
        .form-group input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background: white;
            box-sizing: border-box;
        }



        #grupo_fin_contrato {
            display: none;
        }
    </style>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container">
        <div class="sub-container">
            <!-- Renderizar header universal -->
            <?php echo renderHeader($usuario, 'Registrar Nuevo Colaborador'); ?>

            <div class="container">

                <div class="form-container">
                    <h1 style="display:none;" class="title">Registrar Nuevo Colaborador</h1>

                    <?php if (isset($_SESSION['exito']) && !$exito): ?>
                        <div class="alert alert-success">
                            <?= $_SESSION['exito'] ?>
                            <?php unset($_SESSION['exito']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-error">
                            <strong>Errores encontrados:</strong>
                            <ul>
                                <?php foreach ($errores as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div style="display:none;" class="info-box">
                        <p><strong>Información importante:</strong></p>
                        <p>El sistema generará automáticamente:</p>
                        <ul>
                            <li>El código de operario al guardar</li>
                            <li>El usuario con las primeras 2 letras del nombre + primeras 2 letras del apellido +
                                últimos 4
                                dígitos de cédula</li>
                            <li>La clave con las primeras 2 letras del nombre + primeras 2 letras del apellido + fecha
                                actual
                                (ddmmyy)</li>
                            <li>El estado del colaborador se establecerá como "Activo"</li>
                            <li>Se registrará que fue creado por:
                                <?= false ? htmlspecialchars($usuario['nombre']) : htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?>
                                (ID: <?= $usuarioActualId ?>)
                            </li>
                            <li>Después de guardar, serás redirigido automáticamente a la página de edición del
                                colaborador</li>
                        </ul>
                    </div>

                    <!-- Caja de previsualización -->
                    <div class="preview-box">
                        <h3 style="display:none;">Previsualización de credenciales</h3>
                        <div class="preview-item">
                            <span class="preview-label">Código:</span>
                            <span class="preview-value" id="preview-codigo"><?= $siguienteCodigo ?></span>
                        </div>
                        <div style="display:none;" class="registrado-por">
                            Registrado por:
                            <?= false ? htmlspecialchars($usuario['nombre']) : htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?>
                        </div>
                    </div>

                    <form method="post" action="nuevo_colaborador.php">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="Nombre">Primer Nombre *</label>
                                    <input type="text" id="Nombre" name="Nombre"
                                        value="<?= htmlspecialchars($valores['Nombre']) ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="Nombre2">Segundo Nombre</label>
                                    <input type="text" id="Nombre2" name="Nombre2"
                                        value="<?= htmlspecialchars($valores['Nombre2']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="Apellido">Primer Apellido *</label>
                                    <input type="text" id="Apellido" name="Apellido"
                                        value="<?= htmlspecialchars($valores['Apellido']) ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="Apellido2">Segundo Apellido</label>
                                    <input type="text" id="Apellido2" name="Apellido2"
                                        value="<?= htmlspecialchars($valores['Apellido2']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="Cedula">Cédula *</label>
                            <input type="text" id="Cedula" name="Cedula"
                                value="<?= htmlspecialchars($valores['Cedula']) ?>" required
                                pattern="[0-9]{3}-[0-9]{6}-[0-9]{4}[A-Za-z]?" title="Formato: 001-234567-8910A"
                                placeholder="001-234567-8910A" maxlength="16">
                        </div>

                        <div class="form-group">
                            <label for="Celular">Celular</label>
                            <input type="text" id="Celular" name="Celular"
                                value="<?= htmlspecialchars($valores['Celular']) ?>" placeholder="Número de celular"
                                maxlength="8">
                        </div>

                        <!-- Mover Usuario y Clave aquí, debajo de Cédula -->
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="usuario">Usuario</label>
                                    <input readonly type="text" id="usuario" name="usuario"
                                        value="<?= htmlspecialchars($valores['usuario']) ?>"
                                        placeholder="Se generará automáticamente">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="clave">Clave</label>
                                    <input readonly type="text" id="clave" name="clave"
                                        value="<?= htmlspecialchars($valores['clave']) ?>"
                                        placeholder="Se generará automáticamente">
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="Operativo" value="1">

                        <!-- ══════════════════════════════════════════════════ -->
                        <!-- SECCIÓN: DATOS DE CONTRATO (OBLIGATORIOS)         -->
                        <!-- ══════════════════════════════════════════════════ -->
                        <div class="seccion-contrato">
                            <h3 class="seccion-titulo">
                                <i class="fas fa-file-contract"></i>
                                Datos de Contrato
                                <small>— Obligatorio para crear el colaborador</small>
                            </h3>

                            <!-- Código contrato + Tipo contrato -->
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="codigo_manual_contrato">Código de Contrato</label>
                                        <input type="text" id="codigo_manual_contrato" name="codigo_manual_contrato"
                                            value="<?= htmlspecialchars($valores['codigo_manual_contrato']) ?>"
                                            placeholder="Ej: CONT-2024-001">
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="cod_tipo_contrato">Tipo de Contrato</label>
                                        <select id="cod_tipo_contrato" name="cod_tipo_contrato" required>
                                            <option value="">Seleccionar tipo...</option>
                                            <?php foreach (ncGetTiposContrato() as $tipo): ?>
                                                <option value="<?= $tipo['CodTipoContrato'] ?>"
                                                    <?= $valores['cod_tipo_contrato'] == $tipo['CodTipoContrato'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($tipo['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Cargo + Sucursal -->
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="cod_cargo">Cargo</label>
                                        <select id="cod_cargo" name="cod_cargo" required>
                                            <option value="">Seleccionar cargo...</option>
                                            <?php foreach (ncGetCargos() as $cargo): ?>
                                                <option value="<?= $cargo['CodNivelesCargos'] ?>"
                                                    <?= $valores['cod_cargo'] == $cargo['CodNivelesCargos'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cargo['Nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="sucursal">Área / Sucursal</label>
                                        <select id="sucursal" name="sucursal" required>
                                            <option value="">Seleccionar sucursal...</option>
                                            <?php foreach (obtenerTodasSucursales() as $suc): ?>
                                                <option value="<?= htmlspecialchars($suc['codigo']) ?>"
                                                    <?= $valores['sucursal'] == $suc['codigo'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($suc['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Fecha de Inicio -->
                            <div class="form-row">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="inicio_contrato">Fecha de Inicio</label>
                                        <input type="date" id="inicio_contrato" name="inicio_contrato"
                                            value="<?= htmlspecialchars($valores['inicio_contrato']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-col"></div>
                            </div>

                            <!-- Fecha fin: visible solo cuando tipo = Determinado -->
                            <div class="form-row" id="grupo_fin_contrato">
                                <div class="form-col">
                                    <div class="form-group">
                                        <label for="fin_contrato">Fecha de Fin de Contrato</label>
                                        <input type="date" id="fin_contrato" name="fin_contrato"
                                            value="<?= htmlspecialchars($valores['fin_contrato'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="form-col"></div>
                            </div>

                        </div>
                        <!-- FIN SECCIÓN CONTRATO -->

                        <button type="submit" class="btn-submit" style="margin-top:20px;">
                            <i class="fas fa-save"></i> Crear Colaborador
                        </button>
                    </form>
                </div>
            </div> <!-- cierre container -->
        </div> <!-- cierre sub-container -->
    </div> <!-- cierre main-container -->

    <script>
        // Script para generar automáticamente usuario y clave - VERSIÓN MEJORADA
        document.addEventListener('DOMContentLoaded', function () {
            const nombreInput = document.getElementById('Nombre');
            const apellidoInput = document.getElementById('Apellido');
            const cedulaInput = document.getElementById('Cedula');
            const usuarioInput = document.getElementById('usuario');
            const claveInput = document.getElementById('clave');

            // Bandera para controlar si el usuario ha editado manualmente los campos
            let usuarioEditadoManualmente = false;
            let claveEditadaManualmente = false;

            function actualizarCredenciales() {
                const nombre = nombreInput.value.trim();
                const apellido = apellidoInput.value.trim();
                const cedula = cedulaInput.value.trim();

                // Generar usuario con lo que haya disponible
                let inicialNombre = '';
                let inicialApellido = '';
                let digitosCedula = '';
                let ultimosDigitos = '';
                let letraCedula = '';

                // Obtener iniciales de nombre (primeras 2 letras o lo que haya)
                if (nombre) {
                    inicialNombre = nombre.substring(0, 2).toLowerCase();
                } else {
                    inicialNombre = 'xx'; // Valor por defecto si no hay nombre
                }

                // Obtener iniciales de apellido (primeras 2 letras o lo que haya)
                if (apellido) {
                    inicialApellido = apellido.substring(0, 2).toLowerCase();
                } else {
                    inicialApellido = 'xx'; // Valor por defecto si no hay apellido
                }

                // Procesar cédula si existe
                if (cedula) {
                    digitosCedula = cedula.replace(/[^0-9]/g, '');

                    if (digitosCedula.length >= 3) {
                        ultimosDigitos = digitosCedula.substring(digitosCedula.length - 3);
                    } else if (digitosCedula.length > 0) {
                        ultimosDigitos = digitosCedula.padStart(3, '0');
                    } else {
                        ultimosDigitos = '000'; // Valor por defecto si no hay cédula
                    }

                    // Obtener la última letra de la cédula (si existe)
                    if (/[A-Za-z]$/.test(cedula)) {
                        letraCedula = cedula.substring(cedula.length - 1).toLowerCase();
                    }
                } else {
                    ultimosDigitos = '000'; // Valor por defecto si no hay cédula
                }

                const usuarioGenerado = inicialNombre + inicialApellido + ultimosDigitos + letraCedula;

                // Generar clave con lo que haya disponible
                const fecha = new Date();
                const dia = fecha.getDate().toString().padStart(2, '0');
                const mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
                const anio = fecha.getFullYear().toString().substring(2);

                const claveGenerada = inicialNombre + inicialApellido + dia + mes + anio;

                // Actualizar los campos de entrada solo si no han sido editados manualmente
                if (!usuarioEditadoManualmente) {
                    usuarioInput.value = usuarioGenerado;
                }
                if (!claveEditadaManualmente) {
                    claveInput.value = claveGenerada;
                }
            }

            // Detectar cuando el usuario edita manualmente los campos
            usuarioInput.addEventListener('input', function () {
                usuarioEditadoManualmente = this.value !== '';
            });

            claveInput.addEventListener('input', function () {
                claveEditadaManualmente = this.value !== '';
            });

            // Escuchar cambios en TODOS los campos principales (incluyendo borrado)
            nombreInput.addEventListener('input', actualizarCredenciales);
            nombreInput.addEventListener('change', actualizarCredenciales);

            apellidoInput.addEventListener('input', actualizarCredenciales);
            apellidoInput.addEventListener('change', actualizarCredenciales);

            cedulaInput.addEventListener('input', function () {
                // Primero formatear la cédula
                formatearCedula(this);
                // Luego actualizar credenciales
                actualizarCredenciales();
            });
            cedulaInput.addEventListener('change', actualizarCredenciales);

            // También actualizar cuando la página carga por primera vez
            actualizarCredenciales();
        });

        // Función para formatear la cédula (mantener la existente)
        function formatearCedula(input) {
            // Obtener valor sin guiones y mantener cualquier letra al final
            let value = input.value.replace(/-/g, '');

            // Guardar la posición del cursor
            const startPos = input.selectionStart;

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
            input.value = formattedValue;

            // Ajustar la posición del cursor
            let adjustedPos = startPos;

            // Si agregamos guiones antes de la posición actual, ajustar
            if (startPos >= 3 && numbers.length >= 3) adjustedPos++;
            if (startPos >= 9 && numbers.length >= 9) adjustedPos++;

            // Asegurarse de que no exceda la longitud
            if (adjustedPos > formattedValue.length) {
                adjustedPos = formattedValue.length;
            }

            input.setSelectionRange(adjustedPos, adjustedPos);
        }

        // ── Control fin_contrato: mostrar solo cuando tipo = Determinado ──────
        document.addEventListener('DOMContentLoaded', function () {
            const selectTipo = document.getElementById('cod_tipo_contrato');
            const grupoFin   = document.getElementById('grupo_fin_contrato');
            const inputFin   = document.getElementById('fin_contrato');

            function toggleFinContrato() {
                // CodTipoContrato 1 = Determinado (requiere fecha fin)
                const esDeterminado = selectTipo.value === '1';
                grupoFin.style.display = esDeterminado ? 'flex' : 'none';
                inputFin.required = esDeterminado;
                if (!esDeterminado) inputFin.value = '';
            }

            selectTipo.addEventListener('change', toggleFinContrato);
            toggleFinContrato(); // Estado inicial
        });

        // Antes de enviar el formulario, quitar los guiones para guardar solo números y letra
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const cedulaInput = document.getElementById('Cedula');

            if (form && cedulaInput) {
                form.addEventListener('submit', function () {
                    // Mantener la letra al final pero eliminar guiones
                    const value = cedulaInput.value;
                    let numbers = value.replace(/[^0-9]/g, '');
                    let letter = '';

                    // Extraer la letra final si existe
                    if (value.length > 0 && /[A-Za-z]$/.test(value)) {
                        letter = value.slice(-1);
                    }

                    cedulaInput.value = numbers + letter;
                });
            }
        });
    </script>
</body>

</html>
