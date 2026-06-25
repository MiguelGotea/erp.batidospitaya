<?php
// habilidades.php - Gestión de Habilidades del Portal de Talento
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('talento_contenido', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$canCreate = tienePermiso('talento_contenido', 'crear', $cargoOperario);
$canEdit = tienePermiso('talento_contenido', 'editar', $cargoOperario);
$canDelete = tienePermiso('talento_contenido', 'eliminar', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración Contenido Portal - Habilidades</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    
    <style>
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #0E544C;
            font-weight: bold;
            border-bottom: 3px solid #51B8AC;
        }
        .btn-primary-custom {
            background-color: #51B8AC;
            border-color: #51B8AC;
            color: white;
        }
        .btn-primary-custom:hover {
            background-color: #0E544C;
            border-color: #0E544C;
            color: white;
        }
        .badge-cat-blanda { background-color: rgba(111, 66, 193, 0.12); color: #5a349c; }
        .badge-cat-tecnica { background-color: rgba(0, 123, 255, 0.12); color: #0056b3; }
        .badge-cat-idiomas { background-color: rgba(253, 126, 20, 0.12); color: #a04500; }
        .badge-cat-otros { background-color: rgba(108, 117, 125, 0.12); color: #495057; }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Administración de Contenido Portal'); ?>

            <div class="container-fluid p-4">
                
                <!-- Pestañas de Navegación del Módulo -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link" href="colaboradores.php">
                            <i class="bi bi-people-fill me-2"></i>Colaboradores (Nuestro Equipo)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="habilidades.php">
                            <i class="bi bi-tags-fill me-2"></i>Catálogo de Habilidades
                        </a>
                    </li>
                </ul>

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <!-- Cabecera de la tabla -->
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-0">Catálogo de Habilidades</h5>
                                <p class="text-muted small mb-0">Habilidades disponibles para adjuntar a las plazas vacantes.</p>
                            </div>
                            <?php if ($canCreate): ?>
                            <button class="btn btn-primary-custom d-flex align-items-center gap-2" onclick="abrirModalNuevaHabilidad()">
                                <i class="bi bi-plus-circle-fill"></i> Agregar Habilidad
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Grid/Tabla de habilidades -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tablaHabilidades">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th>Habilidad</th>
                                        <th>Categoría</th>
                                        <th style="width: 100px;" class="text-center">Estado</th>
                                        <th style="width: 150px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyHabilidades">
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Cargando...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Formulario Habilidad -->
    <div class="modal fade" id="modalHabilidad" tabindex="-1" aria-labelledby="modalHabilidadLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formHabilidad">
                    <input type="hidden" id="habilidadId" name="id">
                    
                    <div class="modal-header bg-dark text-white border-0">
                        <h5 class="modal-title fw-bold" id="modalHabilidadLabel">Agregar Habilidad</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Nombre de la habilidad -->
                            <div class="col-12">
                                <label for="habNombre" class="form-label fw-bold">Nombre de la Habilidad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="habNombre" name="nombre" required placeholder="Ej: Trabajo en Equipo">
                            </div>
                            
                            <!-- Categoría -->
                            <div class="col-12">
                                <label for="habCategoria" class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="habCategoria" name="categoria" required>
                                    <option value="" disabled selected>Selecciona una opción</option>
                                    <option value="Habilidades Blandas">Habilidades Blandas</option>
                                    <option value="Habilidades Técnicas">Habilidades Técnicas</option>
                                    <option value="Idiomas">Idiomas</option>
                                    <option value="Otros">Otros</option>
                                </select>
                            </div>
                            
                            <!-- Activo -->
                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="habActivo" name="activo" value="1" checked>
                                    <label class="form-check-label fw-bold" for="habActivo">Activa (disponible para seleccionar)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 p-3 bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom px-4" id="btnGuardarHabilidad">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pasar variables de permisos de PHP a JS -->
    <script>
        const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
        const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
    </script>

    <!-- JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/talento_contenido.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>
