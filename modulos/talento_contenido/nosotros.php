<?php
// nosotros.php - Gestión de Contenido de Nosotros
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
    <title>Administración Contenido - Sobre Nosotros</title>
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
        .audit-info {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 8px 12px;
            margin-top: 15px;
            font-size: 0.82rem;
            color: #555;
            border-left: 3px solid #51B8AC;
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
                        <a class="nav-link" href="noticias.php">
                            <i class="bi bi-newspaper me-2"></i>Noticias y Novedades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="nosotros.php">
                            <i class="bi bi-info-circle-fill me-2"></i>Sobre Nosotros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="configuracion.php">
                            <i class="bi bi-gear-fill me-2"></i>Beneficios y Config.
                        </a>
                    </li>
                </ul>

                <!-- SECCIÓN 0: IMAGEN GRUPAL -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-dark mb-3"><i class="bi bi-image me-2"></i>Imagen Grupal (Foto Principal de Nosotros)</h5>
                        <p class="text-muted small">Imagen de equipo o foto corporativa que aparece en la parte superior de la pestaña "Sobre Nosotros". Formatos: JPG, PNG, WebP, SVG. Máx: 5MB.</p>

                        <div class="row g-4 align-items-center">
                            <!-- Preview -->
                            <div class="col-md-4">
                                <div id="imgNosotrosPreviewBox" style="width:100%; height:200px; border:2px dashed #51B8AC; border-radius:8px; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#f8f9fa;">
                                    <i class="bi bi-image text-muted fs-1" id="imgNosotrosPlaceholder"></i>
                                    <img id="imgNosotrosPreview" src="" alt="Preview" style="display:none; width:100%; height:100%; object-fit:cover;">
                                </div>
                            </div>
                            <!-- Formulario -->
                            <div class="col-md-8">
                                <form id="formImagenNosotros" enctype="multipart/form-data">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Seleccionar nueva imagen</label>
                                            <input type="file" class="form-control" id="inputImagenNosotros" name="imagen_nosotros" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Texto alternativo (alt)</label>
                                            <input type="text" class="form-control" name="imagen_nosotros_alt" id="txtImgAlt" value="Líderes de Tienda Batidos Pitaya" placeholder="Descripción breve para accesibilidad">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Texto del badge sobre la imagen</label>
                                            <input type="text" class="form-control" name="imagen_nosotros_badge" id="txtImgBadge" value="Líderes que hacen posible la Experiencia WOW">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Ruta actual en servidor</label>
                                            <input type="text" class="form-control bg-light" id="txtImgRutaActual" value="" readonly>
                                        </div>
                                    </div>
                                    <?php if ($canEdit): ?>
                                    <div class="mt-3 text-end">
                                        <button type="submit" class="btn btn-primary-custom px-4"><i class="bi bi-cloud-upload me-2"></i>Subir y Guardar Imagen</button>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 1: BIOGRAFÍA E HISTORIA -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-dark mb-3"><i class="bi bi-file-text me-2"></i>Historia y Párrafos de Introducción</h5>
                        <p class="text-muted small">Administración de los párrafos principales del "¿Quiénes Somos?" y de la tarjeta de "Nuestro Propósito".</p>
                        
                        <form id="formTextosNosotros">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Párrafo 1 (Introducción)</label>
                                    <div class="form-text small text-muted">Usa los botones para aplicar formato sin escribir código HTML.</div>
                                    <div class="html-toolbar mb-1">
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo1','b')" title="Negrita"><i class="bi bi-type-bold"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo1','i')" title="Cursiva"><i class="bi bi-type-italic"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo1','p')" title="Párrafo"><i class="bi bi-paragraph"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo1','br')" title="Salto de línea"><i class="bi bi-arrow-return-left"></i></button>
                                    </div>
                                    <textarea class="form-control" name="parrafo_1" id="txtParrafo1" rows="3" required></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Párrafo 2 (Valores y Trabajo en equipo)</label>
                                    <div class="html-toolbar mb-1">
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo2','b')" title="Negrita"><i class="bi bi-type-bold"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo2','i')" title="Cursiva"><i class="bi bi-type-italic"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo2','p')" title="Párrafo"><i class="bi bi-paragraph"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo2','br')" title="Salto de línea"><i class="bi bi-arrow-return-left"></i></button>
                                    </div>
                                    <textarea class="form-control" name="parrafo_2" id="txtParrafo2" rows="3" required></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Párrafo 3 (Visión y Expansión)</label>
                                    <div class="html-toolbar mb-1">
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo3','b')" title="Negrita"><i class="bi bi-type-bold"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo3','i')" title="Cursiva"><i class="bi bi-type-italic"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo3','p')" title="Párrafo"><i class="bi bi-paragraph"></i></button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="insertarHtmlTag('txtParrafo3','br')" title="Salto de línea"><i class="bi bi-arrow-return-left"></i></button>
                                    </div>
                                    <textarea class="form-control" name="parrafo_3" id="txtParrafo3" rows="3" required></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Propósito: Título</label>
                                    <input type="text" class="form-control" name="proposito_titulo" id="txtPropositoTitulo" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Propósito: Descripción</label>
                                    <textarea class="form-control" name="proposito_desc" id="txtPropositoDesc" rows="2" required></textarea>
                                </div>
                            </div>
                            <?php if ($canEdit): ?>
                            <div class="mt-3 text-end">
                                <button type="submit" class="btn btn-primary-custom px-4"><i class="bi bi-save me-2"></i>Guardar Cambios de Texto</button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN 2: VALORES CORPORATIVOS -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-0"><i class="bi bi-gem me-2"></i>Valores Corporativos</h5>
                                <p class="text-muted small mb-0">Gestión de las tarjetas de valores en la sección Nosotros.</p>
                            </div>
                            <?php if ($canCreate): ?>
                            <button class="btn btn-primary-custom btn-sm" onclick="abrirModalValor()"><i class="bi bi-plus-circle me-1"></i>Agregar Valor</button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Icono</th>
                                        <th>Título</th>
                                        <th>Descripción</th>
                                        <th style="width: 80px;" class="text-center">Orden</th>
                                        <th style="width: 100px;" class="text-center">Estado</th>
                                        <th style="width: 150px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyValores">
                                    <tr><td colspan="6" class="text-center py-4">Cargando valores...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: ESTADÍSTICAS -->
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-0"><i class="bi bi-bar-chart-fill me-2"></i>Estadísticas e Indicadores</h5>
                                <p class="text-muted small mb-0">Gestión de los 4 indicadores numéricos animados.</p>
                            </div>
                            <?php if ($canCreate): ?>
                            <button class="btn btn-primary-custom btn-sm" onclick="abrirModalEstadistica()"><i class="bi bi-plus-circle me-1"></i>Agregar Indicador</button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 100px;">Icono</th>
                                        <th>Número</th>
                                        <th>Sufijo</th>
                                        <th>Etiqueta</th>
                                        <th style="width: 80px;" class="text-center">Orden</th>
                                        <th style="width: 100px;" class="text-center">Estado</th>
                                        <th style="width: 150px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyEstadisticas">
                                    <tr><td colspan="7" class="text-center py-4">Cargando estadísticas...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL: AGREGAR/EDITAR VALOR -->
    <div class="modal fade" id="modalValor" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formValor">
                    <input type="hidden" name="id" id="valorId">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold" id="modalValorLabel">Valor Corporativo</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Clase del Icono (Bootstrap Icons) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="icono" id="valIcono" required placeholder="Ej: bi-stars">
                                <div class="form-text small">Puedes buscar clases en <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a> (ej: bi-shield-check, bi-people-fill).</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Título <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="titulo" id="valTitulo" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Descripción <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="descripcion" id="valDescripcion" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Orden de Aparición</label>
                                <input type="number" class="form-control" name="orden" id="valOrden" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Estado</label>
                                <select class="form-select" name="activo" id="valActivo">
                                    <option value="1">Activo (Visible)</option>
                                    <option value="0">Inactivo (Oculto)</option>
                                </select>
                            </div>
                            <!-- Auditoría -->
                            <div class="col-12" id="valAuditBox" style="display: none;">
                                <div class="audit-info">
                                    <div><strong>Creador:</strong> <span id="valAuditCreador"></span></div>
                                    <div id="valAuditModificaRow" style="display: none;"><strong>Modificado por:</strong> <span id="valAuditModificador"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom px-4">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: AGREGAR/EDITAR ESTADÍSTICA -->
    <div class="modal fade" id="modalEstadistica" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formEstadistica">
                    <input type="hidden" name="id" id="statId">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold" id="modalEstadisticaLabel">Estadística/Indicador</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Clase del Icono <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="icono" id="statIcono" required placeholder="Ej: bi-shop, o svg:pitaya para rodaja de pitaya">
                                <div class="form-text small">Escribe <code>svg:pitaya</code> si deseas mostrar el icono especial de Pitaya, o cualquier clase de Bootstrap Icons.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Número de la Estadística <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="valor_numero" id="statValor" required placeholder="Ej: 1.5M, 2016, 14">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Sufijo del Número</label>
                                <input type="text" class="form-control" name="sufijo" id="statSufijo" placeholder="Ej: %, +, K">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Etiqueta/Texto descriptivo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="etiqueta" id="statEtiqueta" required placeholder="Ej: Presentes desde, Sucursales">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Orden de Aparición</label>
                                <input type="number" class="form-control" name="orden" id="statOrden" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Estado</label>
                                <select class="form-select" name="activo" id="statActivo">
                                    <option value="1">Activo (Visible)</option>
                                    <option value="0">Inactivo (Oculto)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Color de Fondo de la Tarjeta</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-color" name="color_fondo" id="statColorFondo" value="#FFC80C" title="Elige el color de fondo">
                                    <input type="text" class="form-control form-control-sm" id="statColorFondoHex" placeholder="#FFC80C" style="width:100px"
                                           oninput="document.getElementById('statColorFondo').value=this.value">
                                </div>
                                <div class="form-text small">Color de la tarjeta en el portal. Deja el predeterminado si no sabes cuál elegir.</div>
                            </div>
                            <!-- Auditoría -->
                            <div class="col-12" id="statAuditBox" style="display: none;">
                                <div class="audit-info">
                                    <div><strong>Creador:</strong> <span id="statAuditCreador"></span></div>
                                    <div id="statAuditModificaRow" style="display: none;"><strong>Modificado por:</strong> <span id="statAuditModificador"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary-custom px-4">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
        const canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;
        
        let modalValInstance;
        let modalStatInstance;
        
        let listadoValores = [];
        let listadoEstadisticas = [];

        $(document).ready(function() {
            modalValInstance = new bootstrap.Modal(document.getElementById('modalValor'));
            modalStatInstance = new bootstrap.Modal(document.getElementById('modalEstadistica'));
            
            cargarImagen();
            cargarTextos();
            cargarValores();
            cargarEstadisticas();

            // Preview de imagen al seleccionar archivo
            $('#inputImagenNosotros').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#imgNosotrosPreview').attr('src', e.target.result).show();
                        $('#imgNosotrosPlaceholder').hide();
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Subir imagen
            $('#formImagenNosotros').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                if (!$('#inputImagenNosotros')[0].files.length) {
                    Swal.fire('Aviso', 'Selecciona una imagen antes de guardar.', 'info');
                    return;
                }
                const btn = $(this).find('[type=submit]').prop('disabled', true).text('Subiendo...');
                $.ajax({
                    url: 'ajax/guardar_imagen_nosotros.php',
                    method: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="bi bi-cloud-upload me-2"></i>Subir y Guardar Imagen');
                        Swal.fire('¡Éxito!', res.mensaje, 'success');
                        $('#txtImgRutaActual').val(res.ruta_publica);
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<i class="bi bi-cloud-upload me-2"></i>Subir y Guardar Imagen');
                        let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error de comunicación';
                        Swal.fire('Error', err, 'error');
                    }
                });
            });

            // Guardar textos
            $('#formTextosNosotros').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'ajax/guardar_textos_nosotros.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        Swal.fire('¡Éxito!', res.mensaje, 'success');
                    },
                    error: function(xhr) {
                        let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error desconocido';
                        Swal.fire('Error', err, 'error');
                    }
                });
            });

            // Guardar valor
            $('#formValor').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'ajax/guardar_valor.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        modalValInstance.hide();
                        Swal.fire('¡Éxito!', res.mensaje, 'success');
                        cargarValores();
                    },
                    error: function(xhr) {
                        let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error de comunicación';
                        Swal.fire('Error', err, 'error');
                    }
                });
            });

            // Guardar estadística
            $('#formEstadistica').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'ajax/guardar_estadistica.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        modalStatInstance.hide();
                        Swal.fire('¡Éxito!', res.mensaje, 'success');
                        cargarEstadisticas();
                    },
                    error: function(xhr) {
                        let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error de comunicación';
                        Swal.fire('Error', err, 'error');
                    }
                });
            });
        });

        // --- IMAGEN GRUPAL ---
        function cargarImagen() {
            $.ajax({
                url: 'ajax/get_configuracion.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    const ruta = data.imagen_nosotros || '';
                    const alt  = data.imagen_nosotros_alt   || 'Líderes de Tienda Batidos Pitaya';
                    const badge = data.imagen_nosotros_badge || 'Líderes que hacen posible la Experiencia WOW';

                    $('#txtImgAlt').val(alt);
                    $('#txtImgBadge').val(badge);
                    $('#txtImgRutaActual').val(ruta);

                    if (ruta) {
                        // Construir URL pública del portal para el preview
                        const base = 'https://talento.batidospitaya.com/';
                        $('#imgNosotrosPreview').attr('src', base + ruta).show();
                        $('#imgNosotrosPlaceholder').hide();
                    }
                }
            });
        }

        // --- HISTORIA ---
        function cargarTextos() {
            $.ajax({
                url: 'ajax/get_textos_nosotros.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#txtParrafo1').val(data.parrafo_1 || '');
                    $('#txtParrafo2').val(data.parrafo_2 || '');
                    $('#txtParrafo3').val(data.parrafo_3 || '');
                    $('#txtPropositoTitulo').val(data.proposito_titulo || '');
                    $('#txtPropositoDesc').val(data.proposito_desc || '');
                },
                error: function() {
                    console.error('Error al cargar párrafos corporativos.');
                }
            });
        }

        // --- VALORES ---
        function cargarValores() {
            $.ajax({
                url: 'ajax/get_valores.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    listadoValores = data;
                    let html = '';
                    if (data.length === 0) {
                        html = '<tr><td colspan="6" class="text-center text-muted">No hay valores registrados.</td></tr>';
                    } else {
                        data.forEach(val => {
                            const badge = val.activo == 1 
                                ? '<span class="badge bg-success">Activo</span>' 
                                : '<span class="badge bg-secondary">Inactivo</span>';
                            
                            let btnEditar = canEdit ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarValor(${val.id})"><i class="bi bi-pencil"></i></button>` : '';
                            let btnEliminar = canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarValor(${val.id})"><i class="bi bi-trash"></i></button>` : '';
                            
                            html += `
                                <tr>
                                    <td><i class="bi ${val.icono} fs-4 text-secondary"></i></td>
                                    <td class="fw-bold">${val.titulo}</td>
                                    <td class="text-muted small">${val.descripcion}</td>
                                    <td class="text-center">${val.orden}</td>
                                    <td class="text-center">${badge}</td>
                                    <td class="text-center">${btnEditar}${btnEliminar}</td>
                                </tr>
                            `;
                        });
                    }
                    $('#tbodyValores').html(html);
                }
            });
        }

        function abrirModalValor() {
            $('#formValor')[0].reset();
            $('#valorId').val('');
            $('#modalValorLabel').text('Agregar Valor Corporativo');
            $('#valAuditBox').hide();
            modalValInstance.show();
        }

        function editarValor(id) {
            const val = listadoValores.find(x => x.id == id);
            if (!val) return;
            
            $('#valorId').val(val.id);
            $('#valIcono').val(val.icono);
            $('#valTitulo').val(val.titulo);
            $('#valDescripcion').val(val.descripcion);
            $('#valOrden').val(val.orden);
            $('#valActivo').val(val.activo);

            // Cargar datos de auditoría
            $('#valAuditCreador').text(`${val.creador_nombre || 'N/D'} el ${val.fecha_creacion}`);
            if (val.usuario_modifica) {
                $('#valAuditModificador').text(`${val.modificador_nombre || 'N/D'} el ${val.fecha_modificacion}`);
                $('#valAuditModificaRow').show();
            } else {
                $('#valAuditModificaRow').hide();
            }
            $('#valAuditBox').show();

            $('#modalValorLabel').text('Editar Valor Corporativo');
            modalValInstance.show();
        }

        function eliminarValor(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede revertir.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax/eliminar_valor.php',
                        method: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(res) {
                            Swal.fire('Eliminado', res.mensaje, 'success');
                            cargarValores();
                        },
                        error: function(xhr) {
                            let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error al procesar solicitud';
                            Swal.fire('Error', err, 'error');
                        }
                    });
                }
            });
        }

        // --- ESTADÍSTICAS ---
        function cargarEstadisticas() {
            $.ajax({
                url: 'ajax/get_estadisticas.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    listadoEstadisticas = data;
                    let html = '';
                    if (data.length === 0) {
                        html = '<tr><td colspan="7" class="text-center text-muted">No hay estadísticas registradas.</td></tr>';
                    } else {
                        data.forEach(stat => {
                            const badge = stat.activo == 1 
                                ? '<span class="badge bg-success">Activo</span>' 
                                : '<span class="badge bg-secondary">Inactivo</span>';
                            
                            let iconoPreview = '';
                            if (stat.icono.indexOf('svg:') === 0) {
                                iconoPreview = `<span class="badge bg-dark">SVG: ${stat.icono.replace('svg:', '')}</span>`;
                            } else {
                                iconoPreview = `<i class="bi ${stat.icono} fs-4 text-secondary"></i>`;
                            }
                            
                            let btnEditar = canEdit ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarEstadistica(${stat.id})"><i class="bi bi-pencil"></i></button>` : '';
                            let btnEliminar = canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarEstadistica(${stat.id})"><i class="bi bi-trash"></i></button>` : '';
                            
                            html += `
                                <tr>
                                    <td>${iconoPreview}</td>
                                    <td class="fw-bold">${stat.valor_numero}</td>
                                    <td><code>${stat.sufijo}</code></td>
                                    <td>${stat.etiqueta}</td>
                                    <td class="text-center">${stat.orden}</td>
                                    <td class="text-center">${badge}</td>
                                    <td class="text-center">${btnEditar}${btnEliminar}</td>
                                </tr>
                            `;
                        });
                    }
                    $('#tbodyEstadisticas').html(html);
                }
            });
        }

        function abrirModalEstadistica() {
            $('#formEstadistica')[0].reset();
            $('#statId').val('');
            $('#modalEstadisticaLabel').text('Agregar Estadística/Indicador');
            $('#statAuditBox').hide();
            modalStatInstance.show();
        }

        function editarEstadistica(id) {
            const stat = listadoEstadisticas.find(x => x.id == id);
            if (!stat) return;
            
            $('#statId').val(stat.id);
            $('#statIcono').val(stat.icono);
            $('#statValor').val(stat.valor_numero);
            $('#statSufijo').val(stat.sufijo);
            $('#statEtiqueta').val(stat.etiqueta);
            $('#statOrden').val(stat.orden);
            $('#statActivo').val(stat.activo);
            const cf = stat.color_fondo || '#FFC80C';
            $('#statColorFondo').val(cf);
            $('#statColorFondoHex').val(cf);

            // Cargar datos de auditoría
            $('#statAuditCreador').text(`${stat.creador_nombre || 'N/D'} el ${stat.fecha_creacion}`);
            if (stat.usuario_modifica) {
                $('#statAuditModificador').text(`${stat.modificador_nombre || 'N/D'} el ${stat.fecha_modificacion}`);
                $('#statAuditModificaRow').show();
            } else {
                $('#statAuditModificaRow').hide();
            }
            $('#statAuditBox').show();

            $('#modalEstadisticaLabel').text('Editar Estadística/Indicador');
            modalStatInstance.show();
        }

        function eliminarEstadistica(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede revertir.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'ajax/eliminar_estadistica.php',
                        method: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(res) {
                            Swal.fire('Eliminado', res.mensaje, 'success');
                            cargarEstadisticas();
                        },
                        error: function(xhr) {
                            let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error al procesar solicitud';
                            Swal.fire('Error', err, 'error');
                        }
                    });
                }
            });
        }
        // Sincronizar hex input con color picker
        $('#statColorFondo').on('input', function() { $('#statColorFondoHex').val(this.value); });

        /**
         * Inserta una etiqueta HTML alrededor del texto seleccionado en un textarea.
         * Permite formatear contenido sin saber HTML manualmente.
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
