<?php
// colaboradores.php - Gestión de Colaboradores del Portal de Talento
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
    <title>Administración Contenido Portal - Colaboradores</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    
    <style>
        .colaborador-thumb {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #51B8AC;
        }
        .avatar-initials {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            font-size: 1.1rem;
        }
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
        .preview-img-box {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 3px solid #51B8AC;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
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
                        <a class="nav-link active" href="colaboradores.php">
                            <i class="bi bi-people-fill me-2"></i>Colaboradores (Nuestro Equipo)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="habilidades.php">
                            <i class="bi bi-tags-fill me-2"></i>Catálogo de Habilidades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="noticias.php">
                            <i class="bi bi-newspaper me-2"></i>Noticias y Novedades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nosotros.php">
                            <i class="bi bi-info-circle-fill me-2"></i>Sobre Nosotros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="configuracion.php">
                            <i class="bi bi-gear-fill me-2"></i>Beneficios y Config.
                        </a>
                    </li>
                </ul>

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <!-- Cabecera de la tabla -->
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-0">Colaboradores Destacados</h5>
                                <p class="text-muted small mb-0">Gestión de testimonios y fotografías para el carrusel público.</p>
                            </div>
                            <?php if ($canCreate): ?>
                            <button class="btn btn-primary-custom d-flex align-items-center gap-2" onclick="abrirModalNuevoColaborador()">
                                <i class="bi bi-plus-circle-fill"></i> Agregar Colaborador
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Grid/Tabla de colaboradores -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tablaColaboradores">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 70px;">Foto</th>
                                        <th>Nombre</th>
                                        <th>Cargo</th>
                                        <th>Departamento</th>
                                        <th style="max-width: 300px;">Testimonio</th>
                                        <th style="width: 80px;" class="text-center">Orden</th>
                                        <th style="width: 90px;" class="text-center">Estado</th>
                                        <th style="width: 120px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyColaboradores">
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
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

    <!-- Modal Formulario Colaborador -->
    <div class="modal fade" id="modalColaborador" tabindex="-1" aria-labelledby="modalColaboradorLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formColaborador" enctype="multipart/form-data">
                    <input type="hidden" id="colaboradorId" name="id">
                    
                    <div class="modal-header bg-dark text-white border-0">
                        <h5 class="modal-title fw-bold" id="modalColaboradorLabel">Agregar Colaborador</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Nombre completo -->
                            <div class="col-md-6">
                                <label for="colNombre" class="form-label fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="colNombre" name="nombre" required placeholder="Ej: María González">
                            </div>
                            
                            <!-- Cargo -->
                            <div class="col-md-6">
                                <label for="colCargo" class="form-label fw-bold">Cargo / Puesto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="colCargo" name="cargo" required placeholder="Ej: Líder de Tienda">
                            </div>
                            
                            <!-- Departamento (Ocultado del modal por solicitud, no requerido) -->
                            <div class="col-md-6" style="display: none;">
                                <label for="colDepartamento" class="form-label fw-bold">Departamento / Área</label>
                                <select class="form-select" id="colDepartamento" name="departamento">
                                    <option value="General" selected>General</option>
                                    <option value="Operaciones y Tiendas">Operaciones y Tiendas</option>
                                    <option value="Logística y Suministros">Logística y Suministros</option>
                                    <option value="Administración y Finanzas">Administración y Finanzas</option>
                                    <option value="Talento y Cultura">Talento y Cultura</option>
                                    <option value="Producción y CDS">Producción y CDS</option>
                                </select>
                            </div>
                            
                            <!-- Orden de aparición -->
                            <div class="col-md-3">
                                <label for="colOrden" class="form-label fw-bold">Orden <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="colOrden" name="orden" min="0" value="0" required>
                            </div>
                            
                            <!-- Activo -->
                            <div class="col-md-3 d-flex align-items-end pb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="colActivo" name="activo" value="1" checked>
                                    <label class="form-check-label fw-bold" for="colActivo">Activo</label>
                                </div>
                            </div>

                            <!-- Testimonio -->
                            <div class="col-12">
                                <label for="colTestimonio" class="form-label fw-bold">Testimonio o Frase Destacada <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="colTestimonio" name="testimonio" rows="3" required placeholder="Escribe el testimonio del colaborador aquí..."></textarea>
                            </div>

                            <!-- Foto de perfil -->
                            <div class="col-12 border-top pt-3 mt-4">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <div class="preview-img-box" id="fotoPreviewBox">
                                            <i class="bi bi-person-fill text-muted fs-1"></i>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label for="colFoto" class="form-label fw-bold">Fotografía del Colaborador</label>
                                        <input type="file" class="form-control" id="colFoto" name="foto" accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text small">Recomendado: Cuadrada (400x400px), JPG/WebP. Tamaño máx: 50MB.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 p-3 bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom px-4" id="btnGuardarColaborador">Guardar</button>
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
