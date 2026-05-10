<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('registro_producto_global', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Determinar si es nuevo o edición
$idProducto = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$esNuevo = ($idProducto === 0);

// Permisos
$puedeCrear = tienePermiso('registro_producto_global', 'nuevo_registro', $cargoOperario);
$puedeEditar = tienePermiso('registro_producto_global', 'editar', $cargoOperario);

// Si es edición pero no tiene permisos, redirigir
if (!$esNuevo && !$puedeEditar) {
    header('Location: producto_presentacion_gestion.php');
    exit();
}

// Si es nuevo pero no tiene permisos, redirigir
if ($esNuevo && !$puedeCrear) {
    header('Location: producto_presentacion_gestion.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $esNuevo ? 'Nuevo Producto' : 'Editar Producto'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../core/assets/css/global_tools.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/registro_producto_global.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, $esNuevo ? 'Nuevo Producto' : 'Editar Producto'); ?>

            <div class="container-fluid p-3">
                <!-- FORMULARIO PRINCIPAL -->
                <form id="formProducto">
                    <input type="hidden" id="idProducto" name="id" value="<?php echo $idProducto; ?>">

                    <!-- CONTENEDOR DE PESTAÑAS -->
                    <div class="tabs-container">
                        <!-- NAVEGACIÓN DE PESTAÑAS -->
                        <div class="tabs-navigation">
                            <button type="button" class="tab-button active" onclick="cambiarTab('datos-basicos')"
                                data-tab="datos-basicos">
                                <div class="tab-icon">
                                    <i class="bi bi-info-circle"></i>
                                </div>
                                <span class="tab-label">Datos Básicos</span>
                            </button>

                            <button type="button" class="tab-button" onclick="cambiarTab('receta')" data-tab="receta">
                                <div class="tab-icon">
                                    <i class="bi bi-clipboard-check"></i>
                                </div>
                                <span class="tab-label">Receta</span>
                                <span class="tab-badge" id="badgeComponentes" style="display: none;">0</span>
                            </button>

                            <button type="button" class="tab-button" onclick="cambiarTab('variaciones')"
                                data-tab="variaciones">
                                <div class="tab-icon">
                                    <i class="bi bi-palette"></i>
                                </div>
                                <span class="tab-label">Variaciones</span>
                                <span class="tab-badge" id="badgeVariaciones" style="display: none;">0</span>
                            </button>

                            <button type="button" class="tab-button" onclick="cambiarTab('multimedia')"
                                data-tab="multimedia">
                                <div class="tab-icon">
                                    <i class="bi bi-images"></i>
                                </div>
                                <span class="tab-label">Multimedia</span>
                                <span class="tab-badge" id="badgeMultimedia" style="display: none;">0</span>
                            </button>

                            <button type="button" class="tab-button" onclick="cambiarTab('ficha-tecnica')"
                                data-tab="ficha-tecnica">
                                <div class="tab-icon">
                                    <i class="bi bi-card-list"></i>
                                </div>
                                <span class="tab-label">Ficha Técnica</span>
                                <span class="tab-badge" id="badgeFicha" style="display: none;">0</span>
                            </button>
                        </div>

                        <!-- CONTENIDO DE PESTAÑAS -->
                        <div class="tabs-content">
                            <!-- ============================================ -->
                            <!-- PESTAÑA 1: DATOS BÁSICOS -->
                            <!-- ============================================ -->
                            <div class="tab-pane active" id="tab-datos-basicos">
                                <div class="row g-3">
                                    <!-- Fila 1: Identificación y Comercial -->
                                    <div class="col-md-3">
                                        <label for="sku" class="form-label">SKU *</label>
                                        <input type="text" class="form-control" id="sku" name="sku" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="nombre" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="presentacion" class="form-label">Presentación Comercial</label>
                                        <input type="text" class="form-control" id="presentacion" name="presentacion" 
                                            placeholder="Ej: Paquete x 12" 
                                            onchange="guardarCampoInline('presentacion', this.value)">
                                    </div>

                                    <!-- Fila 2: Clasificación y Logística -->
                                    <div class="col-md-4">
                                        <label for="grupo" class="form-label">Grupo</label>
                                        <select class="form-select" id="grupo" name="id_grupo" onchange="cargarSubgrupos()">
                                            <option value="">Seleccione...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="subgrupo" class="form-label">Subgrupo</label>
                                        <select class="form-select" id="subgrupo" name="id_subgrupo_presentacion_producto">
                                            <option value="">Seleccione grupo primero...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="categoriaInsumo" class="form-label">Categoría Insumo (Mapeo)</label>
                                        <select class="form-select" id="categoriaInsumo" name="categoria_insumo" onchange="guardarCampoInline('categoria_insumo', this.value)">
                                            <option value="">Seleccione...</option>
                                            <option value="A">A - Frescos</option>
                                            <option value="B">B - Congelados</option>
                                            <option value="C">C - Fresas</option>
                                            <option value="D">D - Desechables</option>
                                            <option value="E">E - Fijos</option>
                                            <option value="F">F - Secos y Preparación</option>
                                            <option value="G">G - Productos de Mostrador</option>
                                        </select>
                                    </div>

                                    <!-- Fila 4: Características y Toggles -->
                                    <div class="col-md-6">
                                        <label class="form-label d-block">Características de Uso</label>
                                        <div class="d-flex flex-column gap-2 p-2 border rounded bg-light">
                                            <div class="toggle-container">
                                                <span class="toggle-label" style="font-size: 0.85rem;">Vendible</span>
                                                <label class="switch">
                                                    <input type="checkbox" id="esVendible" name="es_vendible" value="SI">
                                                    <span class="slider round">
                                                        <i class="bi bi-check check-icon"></i>
                                                        <i class="bi bi-x x-icon"></i>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="toggle-container">
                                                <span class="toggle-label" style="font-size: 0.85rem;">Comprable</span>
                                                <label class="switch">
                                                    <input type="checkbox" id="esComprable" name="es_comprable" value="SI">
                                                    <span class="slider round">
                                                        <i class="bi bi-check check-icon"></i>
                                                        <i class="bi bi-x x-icon"></i>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="toggle-container">
                                                <span class="toggle-label" style="font-size: 0.85rem;">Fabricable</span>
                                                <label class="switch">
                                                    <input type="checkbox" id="esFabricable" name="es_fabricable" value="SI">
                                                    <span class="slider round">
                                                        <i class="bi bi-check check-icon"></i>
                                                        <i class="bi bi-x x-icon"></i>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="toggle-container">
                                                <span class="toggle-label" style="font-size: 0.85rem;">¿Es comprable para facturas?</span>
                                                <label class="switch">
                                                    <input type="checkbox" id="compraTienda" name="compra_tienda" value="1">
                                                    <span class="slider round">
                                                        <i class="bi bi-check check-icon"></i>
                                                        <i class="bi bi-x x-icon"></i>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label d-block">Configuraciones Logísticas</label>
                                        <div class="d-flex flex-column gap-2 p-2 border rounded bg-light">
                                            <div class="toggle-container">
                                                <span class="toggle-label" style="font-size: 0.85rem;">Presentación Básica de Inventario</span>
                                                <label class="switch">
                                                    <input type="checkbox" id="presentacionBasica" name="presentacion_basica_inventario" value="1">
                                                    <span class="slider round">
                                                        <i class="bi bi-check check-icon"></i>
                                                        <i class="bi bi-x x-icon"></i>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="toggle-container">
                                                <span class="toggle-label" style="font-size: 0.85rem;">¿Es Producto Despacho?</span>
                                                <label class="switch">
                                                    <input type="checkbox" id="presentacionDespacho" name="presentacion_despacho" value="1">
                                                    <span class="slider round">
                                                        <i class="bi bi-check check-icon"></i>
                                                        <i class="bi bi-x x-icon"></i>
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="toggle-container">
                                                <span class="toggle-label" style="font-size: 0.85rem;">Presentación Receta</span>
                                                <label class="switch">
                                                    <input type="checkbox" id="presentacionReceta" name="presentacion_receta" value="1">
                                                    <span class="slider round">
                                                        <i class="bi bi-check check-icon"></i>
                                                        <i class="bi bi-x x-icon"></i>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================ -->
                            <!-- PESTAÑA 2: RECETA Y COMPONENTES -->
                            <!-- ============================================ -->
                            <div class="tab-pane" id="tab-receta">
                                <div class="row g-3">
                                    <div id="contenedorCamposMaestros" class="row g-3 px-0">
                                        <div class="col-md-6">
                                            <label for="productoMaestro" class="form-label">Producto Maestro *</label>
                                            <select class="form-select" id="productoMaestro" name="id_producto_maestro"
                                                required>
                                                <option value="">Seleccione...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="unidad" class="form-label">Unidad *</label>
                                            <select class="form-select" id="unidad" name="id_unidad_producto" required>
                                                <option value="">Seleccione...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cantidad" class="form-label">Cantidad *</label>
                                            <input type="number" class="form-control" id="cantidad" name="cantidad"
                                                step="0.01" value="0.00" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="toggle-container">
                                            <span class="toggle-label">¿Este producto tiene receta?</span>
                                            <label class="switch">
                                                <input type="checkbox" id="tieneReceta" onchange="toggleReceta()">
                                                <span class="slider round">
                                                    <i class="bi bi-check check-icon"></i>
                                                    <i class="bi bi-x x-icon"></i>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <div id="datosReceta" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="nombreReceta" class="form-label">Nombre de la Receta</label>
                                                <input type="text" class="form-control" id="nombreReceta"
                                                    name="nombre_receta">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="tipoReceta" class="form-label">Tipo de Receta *</label>
                                                <select class="form-select" id="tipoReceta" name="id_tipo_receta">
                                                    <option value="">Seleccione...</option>
                                                </select>
                                            </div>
                                            <div class="col-md-12">
                                                <label for="descripcionReceta" class="form-label">Descripción</label>
                                                <textarea class="form-control" id="descripcionReceta"
                                                    name="descripcion_receta" rows="2"></textarea>
                                            </div>

                                            <!-- Lista de componentes -->
                                            <div class="col-md-12">
                                                <label class="form-label mb-0"><strong>Componentes de la
                                                        Receta</strong></label>
                                            </div>

                                            <!-- Formulario Inline para Componentes -->
                                            <div class="inline-form-container mb-3 p-3 border rounded bg-light">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-5">
                                                        <label class="form-label small">Producto componente *</label>
                                                        <select class="form-select form-select-sm"
                                                            id="inlineComponenteProducto">
                                                            <option value="">Seleccione producto...</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small">Cantidad *</label>
                                                        <input type="number" class="form-control form-control-sm"
                                                            id="inlineComponenteCantidad" step="0.01">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Notas</label>
                                                        <input type="text" class="form-control form-control-sm"
                                                            id="inlineComponenteNotas" placeholder="Opcional">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-sm btn-success w-100"
                                                            onclick="agregarComponenteInline()">
                                                            <i class="bi bi-plus-circle"></i> Agregar
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th width="5%">#</th>
                                                            <th width="40%">Producto</th>
                                                            <th width="20%">Cantidad</th>
                                                            <th width="25%">Notas</th>
                                                            <th width="10%">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tablaComponentes">
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted">
                                                                No hay componentes agregados
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================ -->
                            <!-- PESTAÑA 3: VARIACIONES -->
                            <!-- ============================================ -->
                            <div class="tab-pane" id="tab-variaciones">
                                <div class="inline-form-container mb-3 p-3 border rounded bg-light">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label small">Nombre Variación *</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="inlineVariacionNombre" placeholder="Ej: Rojo, L, XL">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Descripción</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="inlineVariacionDescripcion" placeholder="Opcional">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-sm btn-success w-100"
                                                onclick="agregarVariacionInline()">
                                                <i class="bi bi-plus-circle"></i> Agregar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="listaVariaciones">
                                    <p class="text-center text-muted">No hay variaciones registradas</p>
                                </div>
                            </div>

                            <!-- ============================================ -->
                            <!-- PESTAÑA 4: MULTIMEDIA (FOTOS Y ARCHIVOS) -->
                            <!-- ============================================ -->
                            <div class="tab-pane" id="tab-multimedia">
                                <div class="multimedia-grid">
                                    <!-- Sección de Fotos -->
                                    <div class="multimedia-section">
                                        <div class="multimedia-section-title">
                                            <i class="bi bi-image"></i>
                                            <span>Fotos del Producto</span>
                                        </div>
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-sm btn-primary"
                                                onclick="document.getElementById('inputFoto').click()">
                                                <i class="bi bi-upload"></i> Subir Foto
                                            </button>
                                            <input type="file" id="inputFoto" accept="image/*" style="display:none"
                                                onchange="subirFoto()">
                                            <small class="text-muted d-block mt-1">Formatos: JPG, PNG. Tamaño máximo:
                                                10MB</small>
                                        </div>
                                        <div id="galeriaFotos" class="foto-gallery">
                                            <p class="text-center text-muted">No hay fotos cargadas</p>
                                        </div>
                                    </div>

                                    <!-- Sección de Archivos -->
                                    <div class="multimedia-section">
                                        <div class="multimedia-section-title">
                                            <i class="bi bi-file-earmark-text"></i>
                                            <span>Archivos Adjuntos</span>
                                        </div>
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-sm btn-primary"
                                                onclick="document.getElementById('inputArchivo').click()">
                                                <i class="bi bi-upload"></i> Subir Archivo
                                            </button>
                                            <input type="file" id="inputArchivo" style="display:none"
                                                onchange="subirArchivo()">
                                            <small class="text-muted d-block mt-1">Formatos: PDF, Excel, Word. Tamaño
                                                máximo: 10MB</small>
                                        </div>
                                        <div id="listaArchivos">
                                            <p class="text-center text-muted">No hay archivos adjuntos</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================ -->
                            <!-- PESTAÑA 5: FICHA TÉCNICA -->
                            <!-- ============================================ -->
                            <div class="tab-pane" id="tab-ficha-tecnica">
                                <div class="inline-form-container mb-3 p-3 border rounded bg-light">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label small">Campo *</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="inlineFichaCampo" placeholder="Ej: Material, Peso">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Valor *</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="inlineFichaValor" placeholder="Ej: Algodón, 500g">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-sm btn-success w-100"
                                                onclick="agregarFichaInline()">
                                                <i class="bi bi-plus-circle"></i> Agregar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="listaFichaTecnica">
                                    <p class="text-center text-muted">No hay campos en la ficha técnica</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción finales -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Guardar Producto
                        </button>
                        <a href="producto_presentacion_gestion.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Vista Previa de Imágenes -->
    <div id="imagePreviewModal" class="preview-modal">
        <span class="preview-close">&times;</span>
        <img class="preview-content" id="imgPreview">
        <div id="previewCaption"></div>
    </div>


    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/registro_producto_global.js?v=<?php echo time(); ?>"></script>
</body>

</html>