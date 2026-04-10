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
$puedeEditar = tienePermiso('clientes_club_pos', 'edicion', $cargoOperario);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cliente | Batidos Pitaya</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/material_teal.css">
    
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    
    <style>
        :root {
            --pitaya-teal: #51B8AC;
            --pitaya-teal-dark: #0E544C;
            --pitaya-teal-light: #e6f5f3;
            --pitaya-bg: #f8f9fc;
            --accent-color: #51B8AC;
            --text-main: #2c3e50;
            --text-muted: #64748b;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--pitaya-bg);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }

        .main-container {
            padding-bottom: 3rem;
        }

        /* Profile Header Card */
        .profile-header-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(81, 184, 172, 0.1);
        }

        .profile-header-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, var(--pitaya-teal-light) 0%, transparent 70%);
            z-index: 0;
            opacity: 0.5;
        }

        .profile-info-wrapper {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: var(--pitaya-teal);
            color: white;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            box-shadow: 0 8px 16px rgba(81, 184, 172, 0.3);
            text-transform: uppercase;
        }

        .profile-details h2 {
            margin: 0;
            font-weight: 700;
            color: var(--pitaya-teal-dark);
            font-size: 1.75rem;
        }

        .profile-details .membership-badge {
            background: var(--pitaya-teal-light);
            color: var(--pitaya-teal-dark);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.5rem;
            border: 1px solid rgba(81, 184, 172, 0.2);
        }

        /* Form Container */
        .premium-form-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--pitaya-teal-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: none;
            padding-bottom: 0;
        }

        .section-title i {
            width: 36px;
            height: 36px;
            background: var(--pitaya-teal-light);
            color: var(--pitaya-teal);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        /* Input Styles */
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            border: 1.5px solid #e2e8f0;
            background: #fdfdfd;
            font-size: 0.95rem;
            transition: var(--transition);
            color: var(--text-main);
            font-weight: 500;
        }

        .form-control:focus {
            border-color: var(--pitaya-teal);
            box-shadow: 0 0 0 4px rgba(81, 184, 172, 0.1);
            background: white;
        }

        .form-control:disabled {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        /* Buttons */
        .btn-premium {
            background: var(--pitaya-teal);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(81, 184, 172, 0.3);
        }

        .btn-premium:hover {
            background: var(--pitaya-teal-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(81, 184, 172, 0.4);
            color: white;
        }

        .btn-premium:active {
            transform: translateY(0);
        }

        /* Grid Spacing */
        .row-gap-xl {
            row-gap: 1.5rem;
        }

        .input-group-text {
            border-radius: 12px 0 0 12px;
            border: 1.5px solid #e2e8f0;
            background: #f8f9fc;
            color: var(--text-muted);
        }

        .input-group .form-control {
            border-radius: 0 12px 12px 0;
        }

        /* Flatpickr Premium Tweaks */
        .flatpickr-calendar {
            border-radius: 15px !important;
            box-shadow: 0 15px 45px rgba(0,0,0,0.1) !important;
            border: 1px solid rgba(0,0,0,0.05) !important;
            padding: 5px;
        }

        .flatpickr-months {
            padding: 10px 0;
        }

        .flatpickr-month {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 15px;
        }

        .flatpickr-current-month {
            position: relative !important;
            width: auto !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 0 !important;
            left: 0 !important;
        }

        .flatpickr-monthDropdown-months {
            font-weight: 700 !important;
            color: var(--pitaya-teal-dark) !important;
            font-size: 1.1rem !important;
        }

        .numInputWrapper {
            width: 70px !important;
            background: #f8f9fc;
            border-radius: 6px;
            padding: 2px 5px;
        }

        .numInputWrapper input {
            font-weight: 700 !important;
            color: var(--pitaya-teal-dark) !important;
            font-size: 1.1rem !important;
        }

        .flatpickr-day.selected {
            background: var(--pitaya-teal) !important;
            border-color: var(--pitaya-teal) !important;
        }

        .flatpickr-day:hover {
            background: var(--pitaya-teal-light) !important;
            border-color: var(--pitaya-teal-light) !important;
            color: var(--pitaya-teal-dark) !important;
        }

        @media (max-width: 768px) {
            .profile-info-wrapper {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            .premium-form-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Cliente'); ?>
            
            <div class="container-fluid px-0">
                
                <!-- Profile Summary Header -->
                <div class="profile-header-card animate-fade-in">
                    <div class="profile-info-wrapper">
                        <div class="profile-avatar" id="avatar_display">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="profile-details">
                            <h2 id="fullName_display">Cargando Cliente...</h2>
                            <div class="membership-badge">
                                <i class="bi bi-person-badge-fill"></i>
                                Membresía: <span id="membresia_display"><?php echo htmlspecialchars($membresia); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="premium-form-card">
                    <form id="formCliente">
                        <input type="hidden" id="id_clienteclub" name="id_clienteclub">
                        
                        <!-- Section: Account Info -->
                        <div class="section-title">
                            <i class="bi bi-info-circle"></i>
                            Información de Cuenta
                        </div>
                        
                        <div class="row row-gap-xl">
                            <div class="col-md-4">
                                <label class="form-label">Sucursal de Registro</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control" id="nombre_sucursal" disabled value="Cargando...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fecha de Registro</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                                    <input type="text" class="form-control" id="fecha_registro" disabled value="Cargando...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">N° Membresía</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control" id="membresia_input" value="<?php echo htmlspecialchars($membresia); ?>" disabled>
                                </div>
                            </div>
                        </div>

                        <hr class="my-5 opacity-10">

                        <!-- Section: Personal Data -->
                        <div class="section-title">
                            <i class="bi bi-person-text"></i>
                            Datos del Cliente
                        </div>

                        <div class="row row-gap-xl">
                            <div class="col-md-4">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Ej: Juan" <?php echo !$puedeEditar ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-4">
                                <label for="apellido" class="form-label">Apellido</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" placeholder="Ej: Pérez" <?php echo !$puedeEditar ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col-md-4">
                                <label for="cedula" class="form-label">Cédula de Identidad</label>
                                <input type="text" class="form-control" id="cedula" name="cedula" 
                                       placeholder="001-000000-0000A" maxlength="20"
                                       <?php echo !$puedeEditar ? 'disabled' : ''; ?>>
                            </div>
                        </div>

                        <div class="row row-gap-xl mt-3">
                            <div class="col-md-4">
                                <label for="celular" class="form-label">Teléfono Móvil</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="text" class="form-control" id="celular" name="celular" placeholder="8888 8888" <?php echo !$puedeEditar ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="correo" class="form-label">Email de Contacto</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="correo" name="correo" placeholder="correo@ejemplo.com" <?php echo !$puedeEditar ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-balloon"></i></span>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" <?php echo !$puedeEditar ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                        </div>

                        <?php if ($puedeEditar): ?>
                        <div class="mt-5 pt-4 border-top d-flex justify-content-end">
                            <button type="submit" class="btn btn-premium px-5">
                                <i class="bi bi-shield-check fs-5"></i> Actualizar Información
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
    
    <!-- Flatpickr -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        const CONFIG = {
            membresia: '<?php echo $membresia; ?>',
            puedeEditar: <?php echo $puedeEditar ? 'true' : 'false'; ?>
        };

        // Función para formatear la cédula (estándar Nicaragua)
        function formatearCedula(input) {
            if (!input) return;
            const startPos = input.selectionStart;
            let value = input.value.replace(/-/g, '').toUpperCase();
            
            let numbers = value.replace(/[^0-9]/g, '');
            let letter = '';

            const letterMatches = value.match(/[A-Z]$/);
            if (letterMatches) {
                letter = letterMatches[0];
            }

            if (numbers.length > 13) numbers = numbers.substring(0, 13);

            let formattedValue = numbers;
            if (numbers.length > 9) {
                formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3, 9) + '-' + numbers.substring(9);
            } else if (numbers.length > 3) {
                formattedValue = numbers.substring(0, 3) + '-' + numbers.substring(3);
            }

            if (letter) formattedValue += letter;
            
            input.value = formattedValue;

            // Simple repositioning logic
            if (startPos !== null) {
                // Approximate position adjustment
                let newPos = startPos;
                if (value.length > input.value.length) newPos--;
                if (value.length < input.value.length) newPos++;
                input.setSelectionRange(newPos, newPos);
            }
        }

        document.getElementById('cedula').addEventListener('input', function() {
            formatearCedula(this);
        });

        // Effect to update profile header display
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url.includes('cliente_get_perfil.php')) {
                const nombre = $('#nombre').val() || '';
                const apellido = $('#apellido').val() || '';
                
                if (nombre) {
                    $('#fullName_display').text(nombre + ' ' + apellido);
                    $('#avatar_display').text(nombre.charAt(0));
                }

                // Initialize Flatpickr after data is loaded
                flatpickr("#fecha_nacimiento", {
                    locale: "es",
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "d \\de F \\de Y",
                    allowInput: true,
                    monthSelectorType: 'dropdown',
                    maxDate: "today",
                    showMonths: 1,
                    onReady: function(selectedDates, dateStr, instance) {
                        // Ensure the year input is easily accessible
                        const yearInput = instance.yearElements[0];
                        yearInput.title = "Clic para escribir el año";
                    }
                });
            }
        });
    </script>
    <script src="js/cliente_gestion.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>
