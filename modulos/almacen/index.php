<?php
// /public_html/modulos/almacen/index.php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo Almacén (Código 17 para Jefe de Almacén, 23 para Auxiliar)
// También permitimos admin
if (!verificarAccesoCargo([17, 23, 49, 61])) {
    header('Location: ../index.php');
    exit();
}

// Para el sistema de permisos de "tools"
// registrarPermisoVista('almacen', $cargoOperario); // Esto se hace en la BD
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Almacén - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../core/assets/css/global_tools.css">
    <style>
        .module-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #51B8AC;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #51B8AC;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Módulo de Almacén'); ?>

            <div class="container-fluid p-4">
                <div class="module-header">
                    <h2>Bienvenido al Módulo de Almacén</h2>
                    <p class="text-muted">Gestión de inventarios, entradas, salidas y herramientas.</p>
                </div>

                <div class="row g-4">
                    <!-- Ejemplo de indicadores -->
                    <div class="col-md-3">
                        <div class="stat-card d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-1">Stock Bajo</h6>
                                <h3 class="mb-0">0</h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card d-flex align-items-center justify-content-between" style="border-left-color: #0E544C;">
                            <div>
                                <h6 class="text-muted mb-1">Pedidos Pendientes</h6>
                                <h3 class="mb-0">0</h3>
                            </div>
                            <div class="stat-icon" style="color: #0E544C;">
                                <i class="fas fa-truck-loading"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="stat-card d-flex align-items-center justify-content-between" style="border-left-color: #f39c12;">
                            <div>
                                <h6 class="text-muted mb-1">Herramientas en Uso</h6>
                                <h3 class="mb-0">0</h3>
                            </div>
                            <div class="stat-icon" style="color: #f39c12;">
                                <i class="fas fa-tools"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="alert alert-info border-0 shadow-sm">
                        <i class="fas fa-info-circle me-2"></i>
                        Este módulo está en fase de configuración inicial. Pronto se añadirán las herramientas correspondientes.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>