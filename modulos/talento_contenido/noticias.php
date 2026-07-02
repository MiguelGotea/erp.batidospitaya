<?php
// noticias.php - Gestión de Noticias del Portal de Talento
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
    <title>Administración Contenido Portal - Noticias</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    
    <style>
        .noticia-thumb {
            width: 80px;
            height: 48px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid rgba(81, 184, 172, 0.3);
        }
        .noticia-thumb-placeholder {
            width: 80px;
            height: 48px;
            border-radius: 6px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.2rem;
            border: 1px solid rgba(0, 0, 0, 0.08);
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
        .preview-portada-box {
            width: 100%;
            height: 150px;
            border-radius: 8px;
            border: 2px dashed #51B8AC;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        .preview-portada-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .galeria-item-card {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
        }
        .galeria-item-img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }
        .galeria-item-delete {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.85);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .galeria-item-delete:hover {
            background: rgba(220, 53, 69, 1);
        }
        .dropzone-area {
            border: 2px dashed #51B8AC;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            background: rgba(81, 184, 172, 0.03);
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .dropzone-area:hover {
            background: rgba(81, 184, 172, 0.08);
        }
        #modalNoticia .modal-body, #modalGaleria .modal-body {
            max-height: 70vh;
            overflow-y: auto;
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
                        <a class="nav-link" href="colaboradores.php">
                            <i class="bi bi-people-fill me-2"></i>Colaboradores (Nuestro Equipo)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="habilidades.php">
                            <i class="bi bi-tags-fill me-2"></i>Catálogo de Habilidades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="noticias.php">
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
                                <h5 class="card-title fw-bold text-dark mb-0">Noticias y Novedades</h5>
                                <p class="text-muted small mb-0">Gestión de artículos y galerías de fotos para el portal de talento.</p>
                            </div>
                            <?php if ($canCreate): ?>
                            <button class="btn btn-primary-custom d-flex align-items-center gap-2" onclick="abrirModalNuevaNoticia()">
                                <i class="bi bi-plus-circle-fill"></i> Crear Noticia
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Grid/Tabla de noticias -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tablaNoticias">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 100px;">Portada</th>
                                        <th>Título</th>
                                        <th>Categoría</th>
                                        <th>Fecha Pub.</th>
                                        <th>Autor</th>
                                        <th style="width: 100px;" class="text-center">Estado</th>
                                        <th style="width: 150px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyNoticias">
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
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

    <!-- Modal Formulario Noticia -->
    <div class="modal fade" id="modalNoticia" tabindex="-1" aria-labelledby="modalNoticiaLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <form id="formNoticia" enctype="multipart/form-data">
                    <input type="hidden" id="noticiaId" name="id">
                    
                    <div class="modal-header bg-dark text-white border-0">
                        <h5 class="modal-title fw-bold" id="modalNoticiaLabel">Agregar Noticia</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Título -->
                            <div class="col-md-8">
                                <label for="notTitulo" class="form-label fw-bold">Título de la Noticia <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="notTitulo" name="titulo" required placeholder="Ej: Nueva Apertura: Sucursal Estelí">
                            </div>

                            <!-- Categoría -->
                            <div class="col-md-4">
                                <label for="notCategoria" class="form-label fw-bold">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="notCategoria" name="categoria" required>
                                    <option value="" disabled selected>Selecciona una opción</option>
                                    <option value="Expansión">Expansión</option>
                                    <option value="Bienestar">Bienestar</option>
                                    <option value="Lanzamiento">Lanzamiento</option>
                                    <option value="Cultura">Cultura</option>
                                    <option value="General">General</option>
                                </select>
                            </div>

                            <!-- Autor -->
                            <div class="col-md-4">
                                <label for="notAutor" class="form-label fw-bold">Autor o Departamento <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="notAutor" name="autor" required placeholder="Ej: Talento y Cultura">
                            </div>

                            <!-- Fecha de publicación -->
                            <div class="col-md-4">
                                <label for="notFechaPublicacion" class="form-label fw-bold">Fecha de Publicación <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="notFechaPublicacion" name="fecha_publicacion" required value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <!-- Estado -->
                            <div class="col-md-4">
                                <label for="notEstado" class="form-label fw-bold">Estado <span class="text-danger">*</span></label>
                                <select class="form-select" id="notEstado" name="estado" required>
                                    <option value="borrador">Borrador (No visible en la web)</option>
                                    <option value="publicado">Publicado (Visible en la web)</option>
                                    <option value="archivado">Archivado (Oculto)</option>
                                </select>
                            </div>

                            <!-- Resumen corto -->
                            <div class="col-12">
                                <label for="notResumen" class="form-label fw-bold">Resumen / Extracto <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="notResumen" name="resumen" rows="2" required placeholder="Un extracto corto de la noticia (~150 caracteres) para el listado..."></textarea>
                            </div>

                            <!-- Contenido completo (HTML permitido) -->
                            <div class="col-12">
                                <label for="notContenido" class="form-label fw-bold">Contenido Completo <span class="text-danger">*</span></label>
                                <div class="html-toolbar mb-1" role="group">
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('notContenido','b')" title="Negrita"><i class="bi bi-type-bold"></i> Negrita</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('notContenido','i')" title="Cursiva"><i class="bi bi-type-italic"></i> Cursiva</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('notContenido','p')" title="Párrafo"><i class="bi bi-paragraph"></i> Párrafo</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('notContenido','br')" title="Salto de línea"><i class="bi bi-arrow-return-left"></i> Salto</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('notContenido','ul')" title="Lista"><i class="bi bi-list-ul"></i> Lista</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('notContenido','li')" title="Ítem de lista"><i class="bi bi-dot"></i> Ítem</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('notContenido','h2')" title="Título"><i class="bi bi-type-h2"></i> Título</button>
                                </div>
                                <div class="form-text text-muted small mb-1">Selecciona texto y haz clic en un botón para aplicar formato.</div>
                                <textarea class="form-control" id="notContenido" name="contenido" rows="10" required placeholder="Escribe el artículo completo aquí..."></textarea>
                            </div>

                            <!-- Foto de portada -->
                            <div class="col-12 border-top pt-3 mt-4">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <div class="preview-portada-box" id="portadaPreviewBox">
                                            <i class="bi bi-image text-muted fs-1"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="notPortada" class="form-label fw-bold">Imagen de Portada (Principal)</label>
                                        <input type="file" class="form-control" id="notPortada" name="portada" accept="image/jpeg,image/png,image/webp">
                                        <div class="form-text small">Recomendado: Horizontal (800x450px), JPG/WebP. Tamaño máx: 2MB.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 p-3 bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom px-4" id="btnGuardarNoticia">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Galería de Fotos de Noticia -->
    <div class="modal fade" id="modalGaleria" tabindex="-1" aria-labelledby="modalGaleriaLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold" id="modalGaleriaLabel">Galería de Fotos: Noticia</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <!-- Dropzone / Selector para subir fotos nuevas -->
                    <form id="formSubirFotoGaleria" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" id="galeriaNoticiaId" name="noticia_id">
                        <div class="dropzone-area" onclick="document.getElementById('inputFotoGaleria').click()">
                            <i class="bi bi-cloud-arrow-up-fill fs-1 text-primary-custom"></i>
                            <h6 class="fw-bold mt-2">Haz clic aquí para subir fotos a la galería</h6>
                            <p class="text-muted small mb-0">Formatos permitidos: JPG, PNG, WebP. Máx: 2MB por foto.</p>
                            <input type="file" id="inputFotoGaleria" name="foto_galeria" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="subirFotoGaleriaDirecto()">
                        </div>
                    </form>

                    <!-- Lista de fotos actuales -->
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-1">Fotos en la Galería</h6>
                    <p class="text-muted small mb-3"><i class="bi bi-grip-vertical"></i> Arrastra las fotos para cambiar el orden en que aparecen en el carrusel.</p>
                    <div class="row g-3" id="galeriaFotosContainer">
                        <!-- Renderizado dinámico vía JS -->
                        <div class="col-12 text-center py-4 text-muted">
                            Cargando fotos de la galería...
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script src="js/talento_contenido.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
    <script>
        /**
         * Inserta una etiqueta HTML alrededor del texto seleccionado en un textarea.
         */
        function insertarHtmlTag(idTextarea, tag) {
            const ta = document.getElementById(idTextarea);
            if (!ta) return;
            const s = ta.selectionStart, e = ta.selectionEnd;
            const sel = ta.value.substring(s, e);
            const rep = (tag === 'br') ? sel + '<br>' : `<${tag}>${sel}</${tag}>`;
            ta.value = ta.value.substring(0, s) + rep + ta.value.substring(e);
            ta.focus();
            ta.selectionStart = ta.selectionEnd = s + rep.length;
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }
    </script>
</body>

</html>
