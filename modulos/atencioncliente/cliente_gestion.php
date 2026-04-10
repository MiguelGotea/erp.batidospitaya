<?php
// cliente_gestion.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso básico
if (!tienePermiso('clientes_club_pos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$membresia = isset($_GET['membresia']) ? $_GET['membresia'] : '';
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'view';

$puedeEditar = tienePermiso('clientes_club_pos', 'edicion', $cargoOperario);
$esModoEdicion = ($modo === 'edit' && $puedeEditar);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $esModoEdicion ? 'Editar Cliente' : 'Ver Cliente'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-top: 1rem;
        }
        .form-label {
            font-weight: 600;
            color: #444;
        }
        .form-control:disabled {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            color: #495057;
        }
        .section-title {
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, $esModoEdicion ? 'Editar Cliente' : 'Perfil de Cliente'); ?>
            
            <div class="container-fluid p-4">
                <div class="mb-3">
                    <button class="btn btn-secondary" onclick="window.history.back()">
                        <i class="bi bi-arrow-left"></i> Volver al Historial
                    </button>
                    <?php if (!$esModoEdicion && $puedeEditar): ?>
                        <a href="?membresia=<?php echo urlencode($membresia); ?>&modo=edit" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Modo Edición
                        </a>
                    <?php endif; ?>
                </div>

                <div class="form-container">
                    <form id="formCliente">
                        <input type="hidden" id="id_clienteclub" name="id_clienteclub">
                        
                        <div class="section-title">
                            <i class="bi bi-person-badge fs-4"></i>
                            <h4 class="mb-0">Información de Membresía</h4>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Membresía</label>
                                <input type="text" class="form-control" id="membresia" value="<?php echo htmlspecialchars($membresia); ?>" disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sucursal de Registro</label>
                                <input type="text" class="form-control" id="nombre_sucursal" disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Fecha de Registro</label>
                                <input type="text" class="form-control" id="fecha_registro" disabled>
                            </div>
                        </div>

                        <div class="section-title mt-4">
                            <i class="bi bi-person fs-4"></i>
                            <h4 class="mb-0">Datos Personales</h4>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required <?php echo !$esModoEdicion ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" <?php echo !$esModoEdicion ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="cedula" class="form-label">Cédula</label>
                                <input type="text" class="form-control" id="cedula" name="cedula" 
                                       placeholder="001-000000-0000A" maxlength="20"
                                       <?php echo !$esModoEdicion ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="celular" class="form-label">Celular</label>
                                <input type="text" class="form-control" id="celular" name="celular" <?php echo !$esModoEdicion ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="correo" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="correo" name="correo" <?php echo !$esModoEdicion ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" <?php echo !$esModoEdicion ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Puntos Iniciales</label>
                                <input type="text" class="form-control" id="puntos_iniciales" disabled>
                            </div>
                        </div>

                        <?php if ($esModoEdicion): ?>
                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" onclick="window.history.back()">Cancelar</button>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="bi bi-save"></i> Guardar Cambios
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const CONFIG = {
            membresia: '<?php echo $membresia; ?>',
            modo: '<?php echo $modo; ?>',
            puedeEditar: <?php echo $puedeEditar ? 'true' : 'false'; ?>
        };

        // Función para formatear la cédula (estándar Nicaragua)
        function formatearCedula(input) {
            const startPos = input.selectionStart;
            let value = input.value.replace(/-/g, '').toUpperCase();
            
            let numbers = value.replace(/[^0-9]/g, '');
            let letter = '';

            if (value.length > 0 && /[A-Z]/.test(value.slice(-1))) {
                letter = value.slice(-1);
            }

            if (numbers.length > 13) numbers = numbers.substring(0, 13);

            let formattedValue = numbers;
            if (numbers.length > 9) {
                formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3, 9) + '-' + numbers.substring(9);
            } else if (numbers.length > 3) {
                formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3);
            }

            if (letter) formattedValue += letter;
            
            // Contar cuántos dígitos (no guiones) había antes del cursor originalmente
            let digitsBeforeCursor = 0;
            const originalValue = input.value;
            for (let i = 0; i < startPos; i++) {
                if (originalValue[i] !== '-') digitsBeforeCursor++;
            }

            input.value = formattedValue;

            // Encontrar la nueva posición del cursor
            let newPos = 0;
            let currentDigits = 0;
            while (newPos < formattedValue.length && currentDigits < digitsBeforeCursor) {
                if (formattedValue[newPos] !== '-') currentDigits++;
                newPos++;
            }
            
            if (newPos < formattedValue.length && formattedValue[newPos] === '-') {
                newPos++;
            }

            input.setSelectionRange(newPos, newPos);
        }

        document.getElementById('cedula').addEventListener('input', function() {
            formatearCedula(this);
        });
    </script>
    <script src="js/cliente_gestion.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>
