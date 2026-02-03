<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

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
    'Nombre' => '',
    'Nombre2' => '',
    'Apellido' => '',
    'Apellido2' => '',
    'Cedula' => '',
    'Celular' => '',
    'usuario' => '',
    'clave' => ''
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
    // Recoger y sanitizar datos
    $valores['Nombre'] = trim($_POST['Nombre'] ?? '');
    $valores['Nombre2'] = trim($_POST['Nombre2'] ?? '');
    $valores['Apellido'] = trim($_POST['Apellido'] ?? '');
    $valores['Apellido2'] = trim($_POST['Apellido2'] ?? '');
    $valores['Cedula'] = trim($_POST['Cedula'] ?? '');
    $valores['Celular'] = trim($_POST['Celular'] ?? '');
    $valores['usuario'] = trim($_POST['usuario'] ?? '');
    $valores['clave'] = trim($_POST['clave'] ?? '');

    // Validaciones
    if (empty($valores['Nombre'])) {
        $errores[] = "El primer nombre es obligatorio";
    }

    if (empty($valores['Apellido'])) {
        $errores[] = "El primer apellido es obligatorio";
    }

    if (empty($valores['Cedula'])) {
        $errores[] = "La cédula es obligatoria";
    }

    // Verificar si la cédula ya existe (eliminando guiones para la comparación)
    if (empty($errores)) {
        $cedulaLimpia = str_replace('-', '', $valores['Cedula']);
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Operarios WHERE REPLACE(Cedula, '-', '') = ?");
        $stmt->execute([$cedulaLimpia]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "Ya existe un colaborador con esta cédula";
        }
    }

    // Verificar si el usuario ya existe (si se proporcionó manualmente)
    if (empty($errores) && !empty($valores['usuario'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Operarios WHERE usuario = ?");
        $stmt->execute([$valores['usuario']]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "Ya existe un colaborador con este usuario";
        }
    }

    // Si no hay errores, proceder con el registro
    if (empty($errores)) {
        try {
            // Generar usuario y clave automáticamente si no se proporcionaron
            $usuarioGenerado = empty($valores['usuario']) ?
                generarUsuario($valores['Nombre'], $valores['Apellido'], $valores['Cedula']) :
                $valores['usuario'];

            $claveGenerada = empty($valores['clave']) ?
                generarClave($valores['Nombre'], $valores['Apellido']) :
                $valores['clave'];

            // Insertar en la base de datos con el usuario que registra
            $sql = "INSERT INTO Operarios 
                    (Nombre, Nombre2, Apellido, Apellido2, Cedula, Celular, usuario, clave, Operativo, FechaCreacion, registrado_por) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)";

            $stmt = $conn->prepare($sql);
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

            // Obtener el ID del nuevo colaborador
            $nuevoId = $conn->lastInsertId();

            // Guardar mensaje de éxito en sesión para mostrarlo después de la redirección
            $_SESSION['exito'] = "Colaborador registrado exitosamente. Código: $nuevoId, Usuario: $usuarioGenerado, Clave: $claveGenerada";

            // Redirigir a editar_colaborador.php con el ID del nuevo colaborador
            header("Location: editar_colaborador.php?id=$nuevoId");
            exit();

        } catch (PDOException $e) {
            $errores[] = "Error al registrar el colaborador: " . $e->getMessage();
        }
    }
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
    </style>
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container">
        <div class="sub-container">
            <!-- Renderizar header universal -->
            <?php echo renderHeader($usuario, $esAdmin, 'Registrar Nuevo Colaborador'); ?>

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
                                <?= $esAdmin ? htmlspecialchars($usuario['nombre']) : htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?>
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
                            <?= $esAdmin ? htmlspecialchars($usuario['nombre']) : htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?>
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

                        <button type="submit" class="btn-submit">
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