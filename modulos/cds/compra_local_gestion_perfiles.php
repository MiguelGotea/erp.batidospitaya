<?php
// compra_local_gestion_perfiles.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('compra_local_gestion_perfiles', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeEditar = tienePermiso('compra_local_gestion_perfiles', 'edicion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Perfiles de Despacho</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/compra_local_gestion_perfiles.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Perfiles de Despacho'); ?>

            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Planes de Entrega</h4>
                        <p class="text-muted small">Defina frecuencias y días de despacho para agrupar productos.</p>
                    </div>
                    <?php if ($puedeEditar): ?>
                        <button class="btn btn-primary" onclick="abrirModalPerfil()">
                            <i class="fas fa-plus me-2"></i>Nuevo Perfil
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Lista de Perfiles -->
                <div class="row" id="perfiles-list">
                    <!-- Dinámico con JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Perfil -->
    <div class="modal fade" id="modalPerfil" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form id="formPerfil">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title" id="modalTitle">Crear Perfil</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="perfil-id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre del Perfil</label>
                            <input type="text" class="form-control" name="nombre" id="perfil-nombre"
                                placeholder="Ej: Secos Quincenales" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Periodicidad (Frecuencia)</label>
                            <select class="form-select" name="frecuencia_semanas" id="perfil-frecuencia" required
                                onchange="toggleSemanaReferencia()">
                                <option value="1">Semanal (Cada semana)</option>
                                <option value="2">Quincenal (Cada 2 semanas)</option>
                                <option value="3">Cada 3 semanas</option>
                                <option value="4">Mensual (Cada 4 semanas)</option>
                            </select>
                        </div>

                        <div id="semana-referencia-container" class="mb-3 d-none">
                            <label class="form-label fw-bold">Semana de Inicio</label>
                            <p class="text-muted small mb-2">Para planes mayores a 1 semana, defina en qué semana
                                calendario inicia el ciclo.</p>
                            <select class="form-select" name="semana_referencia" id="perfil-semana-ref">
                                <!-- Se llena dinámicamente con las semanas actuales/próximas -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold d-block mb-2">Días de Despacho</label>
                            <div class="btn-group w-100" role="group">
                                <input type="checkbox" class="btn-check" id="day-lun" name="lunes" value="1">
                                <label class="btn btn-outline-secondary" for="day-lun">L</label>

                                <input type="checkbox" class="btn-check" id="day-mar" name="martes" value="1">
                                <label class="btn btn-outline-secondary" for="day-mar">M</label>

                                <input type="checkbox" class="btn-check" id="day-mie" name="miercoles" value="1">
                                <label class="btn btn-outline-secondary" for="day-mie">M</label>

                                <input type="checkbox" class="btn-check" id="day-jue" name="jueves" value="1">
                                <label class="btn btn-outline-secondary" for="day-jue">J</label>

                                <input type="checkbox" class="btn-check" id="day-vie" name="viernes" value="1">
                                <label class="btn btn-outline-secondary" for="day-vie">V</label>

                                <input type="checkbox" class="btn-check" id="day-sab" name="sabado" value="1">
                                <label class="btn btn-outline-secondary" for="day-sab">S</label>

                                <input type="checkbox" class="btn-check" id="day-dom" name="domingo" value="1">
                                <label class="btn btn-outline-secondary" for="day-dom">D</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Perfil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/compra_local_gestion_perfiles.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>