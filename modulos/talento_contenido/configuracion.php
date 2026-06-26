<?php
// configuracion.php - Gestión de Beneficios y Configuración General
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
    <title>Administración Contenido - Beneficios y Configuración</title>
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
                        <a class="nav-link" href="nosotros.php">
                            <i class="bi bi-info-circle-fill me-2"></i>Sobre Nosotros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="configuracion.php">
                            <i class="bi bi-gear-fill me-2"></i>Beneficios y Config.
                        </a>
                    </li>
                </ul>

                <!-- SECCIÓN 1: CONFIGURACIONES GENERALES Y ENLACES -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h5 class="card-title fw-bold text-dark mb-3"><i class="bi bi-sliders me-2"></i>Contacto, Redes y Encabezados</h5>
                        <p class="text-muted small">Configuración general de enlaces de redes sociales, datos de contacto del pie de página y títulos promocionales.</p>
                        
                        <form id="formConfiguracion">
                            <div class="row g-3">
                                <!-- Contacto -->
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Email Reclutamiento (Usuario)</label>
                                    <input type="text" class="form-control" name="email_reclutamiento" id="cfgEmailUser" required placeholder="Ej: seleccion">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Email (Dominio)</label>
                                    <input type="text" class="form-control" name="email_reclutamiento_dom" id="cfgEmailDom" required placeholder="Ej: batidospitaya.com">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Teléfono Principal</label>
                                    <input type="text" class="form-control" name="telefono_principal" id="cfgTelefono" required placeholder="Ej: +505 8852 0629">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">URL Google Maps (Ubicaciones)</label>
                                    <input type="url" class="form-control" name="url_maps_ubicaciones" id="cfgUrlMaps" required>
                                </div>

                                <!-- Redes sociales -->
                                <div class="col-md-4">
                                    <label class="form-label fw-bold"><i class="bi bi-facebook me-1"></i>Enlace Facebook</label>
                                    <input type="url" class="form-control" name="url_facebook" id="cfgUrlFb" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold"><i class="bi bi-instagram me-1"></i>Enlace Instagram</label>
                                    <input type="url" class="form-control" name="url_instagram" id="cfgUrlIg" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold"><i class="bi bi-linkedin me-1"></i>Enlace LinkedIn</label>
                                    <input type="url" class="form-control" name="url_linkedin" id="cfgUrlIn" required>
                                </div>

                                <!-- Textos Hero de Beneficios -->
                                <div class="col-md-3 border-top pt-3 mt-4">
                                    <label class="form-label fw-bold">Hero Beneficios: Subtítulo</label>
                                    <input type="text" class="form-control" name="hero_beneficios_sub" id="cfgHeroBenSub" required>
                                </div>
                                <div class="col-md-4 border-top pt-3 mt-4">
                                    <label class="form-label fw-bold">Hero Beneficios: Título</label>
                                    <input type="text" class="form-control" name="hero_beneficios_titulo" id="cfgHeroBenTitulo" required>
                                </div>
                                <div class="col-md-5 border-top pt-3 mt-4">
                                    <label class="form-label fw-bold">Hero Beneficios: Descripción</label>
                                    <textarea class="form-control" name="hero_beneficios_desc" id="cfgHeroBenDesc" rows="2" required></textarea>
                                </div>

                                <!-- Textos Cultura -->
                                <div class="col-md-3 border-top pt-3 mt-4">
                                    <label class="form-label fw-bold">Cultura: Título Sección</label>
                                    <input type="text" class="form-control" name="cultura_titulo" id="cfgCulturaTitulo" required>
                                </div>
                                <div class="col-md-4 border-top pt-3 mt-4">
                                    <label class="form-label fw-bold">Cultura: Subtítulo</label>
                                    <input type="text" class="form-control" name="cultura_subtitulo" id="cfgCulturaSub" required>
                                </div>
                                <div class="col-md-5 border-top pt-3 mt-4">
                                    <label class="form-label fw-bold">Cultura: Cita Motivacional</label>
                                    <textarea class="form-control" name="cultura_cita" id="cfgCulturaCita" rows="2" required></textarea>
                                </div>
                            </div>
                            <?php if ($canEdit): ?>
                            <div class="mt-3 text-end">
                                <button type="submit" class="btn btn-primary-custom px-4"><i class="bi bi-save me-2"></i>Guardar Configuración</button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- SECCIÓN 2: BENEFICIOS CLAVE -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-0"><i class="bi bi-gift me-2"></i>Beneficios del Colaborador</h5>
                                <p class="text-muted small mb-0">Gestión de las tarjetas de beneficios principales que se muestran en el portal.</p>
                            </div>
                            <?php if ($canCreate): ?>
                            <button class="btn btn-primary-custom btn-sm" onclick="abrirModalBeneficio()"><i class="bi bi-plus-circle me-1"></i>Agregar Beneficio</button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Icono</th>
                                        <th style="width: 100px;">Tema</th>
                                        <th>Título</th>
                                        <th>Descripción</th>
                                        <th style="width: 80px;" class="text-center">Orden</th>
                                        <th style="width: 100px;" class="text-center">Estado</th>
                                        <th style="width: 150px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyBeneficios">
                                    <tr><td colspan="7" class="text-center py-4">Cargando beneficios...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: CULTURA DE BIENESTAR -->
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="card-title fw-bold text-dark mb-0"><i class="bi bi-patch-check me-2"></i>Checklist de Cultura</h5>
                                <p class="text-muted small mb-0">Gestión de los puntos clave del listado cultural ("¿Qué significa ser parte del equipo?").</p>
                            </div>
                            <?php if ($canCreate): ?>
                            <button class="btn btn-primary-custom btn-sm" onclick="abrirModalCultura()"><i class="bi bi-plus-circle me-1"></i>Agregar Punto</button>
                            <?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Título</th>
                                        <th>Descripción</th>
                                        <th style="width: 80px;" class="text-center">Orden</th>
                                        <th style="width: 100px;" class="text-center">Estado</th>
                                        <th style="width: 150px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyCultura">
                                    <tr><td colspan="5" class="text-center py-4">Cargando checklist de cultura...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL: BENEFICIO -->
    <div class="modal fade" id="modalBeneficio" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formBeneficio">
                    <input type="hidden" name="id" id="benId">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold" id="modalBeneficioLabel">Beneficio Corporativo</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Clase del Icono <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="icono" id="benIcono" required placeholder="Ej: bi-trophy">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Color Tema <span class="text-danger">*</span></label>
                                <select class="form-select" name="color_tema" id="benColor">
                                    <option value="teal">Teal (Verde Menta)</option>
                                    <option value="orange">Orange (Naranja)</option>
                                    <option value="green">Green (Verde)</option>
                                    <option value="blue">Blue (Azul)</option>
                                    <option value="purple">Purple (Morado)</option>
                                    <option value="red">Red (Rojo)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Título <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="titulo" id="benTitulo" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Descripción <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="descripcion" id="benDescripcion" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Orden de Aparición</label>
                                <input type="number" class="form-control" name="orden" id="benOrden" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Estado</label>
                                <select class="form-select" name="activo" id="benActivo">
                                    <option value="1">Activo (Visible)</option>
                                    <option value="0">Inactivo (Oculto)</option>
                                </select>
                            </div>
                            <!-- Auditoría -->
                            <div class="col-12" id="benAuditBox" style="display: none;">
                                <div class="audit-info">
                                    <div><strong>Creador:</strong> <span id="benAuditCreador"></span></div>
                                    <div id="benAuditModificaRow" style="display: none;"><strong>Modificado por:</strong> <span id="benAuditModificador"></span></div>
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

    <!-- MODAL: CULTURA -->
    <div class="modal fade" id="modalCultura" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formCultura">
                    <input type="hidden" name="id" id="cultId">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold" id="modalCulturaLabel">Ítem de Cultura</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Título del Punto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="titulo" id="cultTitulo" required placeholder="Ej: Trabajo en Equipo">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Descripción / Detalle <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="descripcion" id="cultDescripcion" rows="3" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Orden de Aparición</label>
                                <input type="number" class="form-control" name="orden" id="cultOrden" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Estado</label>
                                <select class="form-select" name="activo" id="cultActivo">
                                    <option value="1">Activo (Visible)</option>
                                    <option value="0">Inactivo (Oculto)</option>
                                </select>
                            </div>
                            <!-- Auditoría -->
                            <div class="col-12" id="cultAuditBox" style="display: none;">
                                <div class="audit-info">
                                    <div><strong>Creador:</strong> <span id="cultAuditCreador"></span></div>
                                    <div id="cultAuditModificaRow" style="display: none;"><strong>Modificado por:</strong> <span id="cultAuditModificador"></span></div>
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
        
        let modalBenInstance;
        let modalCultInstance;
        
        let listadoBeneficios = [];
        let listadoCultura = [];

        $(document).ready(function() {
            modalBenInstance = new bootstrap.Modal(document.getElementById('modalBeneficio'));
            modalCultInstance = new bootstrap.Modal(document.getElementById('modalCultura'));
            
            cargarConfiguracion();
            cargarBeneficios();
            cargarCultura();

            // Guardar configuración general
            $('#formConfiguracion').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'ajax/guardar_configuracion.php',
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

            // Guardar beneficio
            $('#formBeneficio').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'ajax/guardar_beneficio.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        modalBenInstance.hide();
                        Swal.fire('¡Éxito!', res.mensaje, 'success');
                        cargarBeneficios();
                    },
                    error: function(xhr) {
                        let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error de comunicación';
                        Swal.fire('Error', err, 'error');
                    }
                });
            });

            // Guardar cultura
            $('#formCultura').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'ajax/guardar_cultura.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        modalCultInstance.hide();
                        Swal.fire('¡Éxito!', res.mensaje, 'success');
                        cargarCultura();
                    },
                    error: function(xhr) {
                        let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error de comunicación';
                        Swal.fire('Error', err, 'error');
                    }
                });
            });
        });

        // --- CONFIGURACIÓN GENERAL ---
        function cargarConfiguracion() {
            $.ajax({
                url: 'ajax/get_configuracion.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    $('#cfgEmailUser').val(data.email_reclutamiento || '');
                    $('#cfgEmailDom').val(data.email_reclutamiento_dom || '');
                    $('#cfgTelefono').val(data.telefono_principal || '');
                    $('#cfgUrlMaps').val(data.url_maps_ubicaciones || '');
                    $('#cfgUrlFb').val(data.url_facebook || '');
                    $('#cfgUrlIg').val(data.url_instagram || '');
                    $('#cfgUrlIn').val(data.url_linkedin || '');
                    $('#cfgHeroBenSub').val(data.hero_beneficios_sub || '');
                    $('#cfgHeroBenTitulo').val(data.hero_beneficios_titulo || '');
                    $('#cfgHeroBenDesc').val(data.hero_beneficios_desc || '');
                    $('#cfgCulturaTitulo').val(data.cultura_titulo || '');
                    $('#cfgCulturaSub').val(data.cultura_subtitulo || '');
                    $('#cfgCulturaCita').val(data.cultura_cita || '');
                },
                error: function() {
                    console.error('Error al cargar configuraciones generales.');
                }
            });
        }

        // --- BENEFICIOS ---
        function cargarBeneficios() {
            $.ajax({
                url: 'ajax/get_beneficios.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    listadoBeneficios = data;
                    let html = '';
                    if (data.length === 0) {
                        html = '<tr><td colspan="7" class="text-center text-muted">No hay beneficios registrados.</td></tr>';
                    } else {
                        data.forEach(ben => {
                            const badge = ben.activo == 1 
                                ? '<span class="badge bg-success">Activo</span>' 
                                : '<span class="badge bg-secondary">Inactivo</span>';
                            
                            let btnEditar = canEdit ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarBeneficio(${ben.id})"><i class="bi bi-pencil"></i></button>` : '';
                            let btnEliminar = canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarBeneficio(${ben.id})"><i class="bi bi-trash"></i></button>` : '';
                            
                            html += `
                                <tr>
                                    <td><i class="bi ${ben.icono} fs-4 text-secondary"></i></td>
                                    <td><span class="badge bg-${ben.color_tema}-soft text-${ben.color_tema}">${ben.color_tema}</span></td>
                                    <td class="fw-bold">${ben.titulo}</td>
                                    <td class="text-muted small">${ben.descripcion}</td>
                                    <td class="text-center">${ben.orden}</td>
                                    <td class="text-center">${badge}</td>
                                    <td class="text-center">${btnEditar}${btnEliminar}</td>
                                </tr>
                            `;
                        });
                    }
                    $('#tbodyBeneficios').html(html);
                }
            });
        }

        function abrirModalBeneficio() {
            $('#formBeneficio')[0].reset();
            $('#benId').val('');
            $('#modalBeneficioLabel').text('Agregar Beneficio');
            $('#benAuditBox').hide();
            modalBenInstance.show();
        }

        function editarBeneficio(id) {
            const ben = listadoBeneficios.find(x => x.id == id);
            if (!ben) return;
            
            $('#benId').val(ben.id);
            $('#benIcono').val(ben.icono);
            $('#benColor').val(ben.color_tema);
            $('#benTitulo').val(ben.titulo);
            $('#benDescripcion').val(ben.descripcion);
            $('#benOrden').val(ben.orden);
            $('#benActivo').val(ben.activo);

            // Cargar datos de auditoría
            $('#benAuditCreador').text(`${ben.creador_nombre || 'N/D'} el ${ben.fecha_creacion}`);
            if (ben.usuario_modifica) {
                $('#benAuditModificador').text(`${ben.modificador_nombre || 'N/D'} el ${ben.fecha_modificacion}`);
                $('#benAuditModificaRow').show();
            } else {
                $('#benAuditModificaRow').hide();
            }
            $('#benAuditBox').show();

            $('#modalBeneficioLabel').text('Editar Beneficio');
            modalBenInstance.show();
        }

        function eliminarBeneficio(id) {
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
                        url: 'ajax/eliminar_beneficio.php',
                        method: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(res) {
                            Swal.fire('Eliminado', res.mensaje, 'success');
                            cargarBeneficios();
                        },
                        error: function(xhr) {
                            let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error al procesar solicitud';
                            Swal.fire('Error', err, 'error');
                        }
                    });
                }
            });
        }

        // --- CULTURA ---
        function cargarCultura() {
            $.ajax({
                url: 'ajax/get_culturas.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    listadoCultura = data;
                    let html = '';
                    if (data.length === 0) {
                        html = '<tr><td colspan="5" class="text-center text-muted">No hay puntos de cultura registrados.</td></tr>';
                    } else {
                        data.forEach(cult => {
                            const badge = cult.activo == 1 
                                ? '<span class="badge bg-success">Activo</span>' 
                                : '<span class="badge bg-secondary">Inactivo</span>';
                            
                            let btnEditar = canEdit ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarCultura(${cult.id})"><i class="bi bi-pencil"></i></button>` : '';
                            let btnEliminar = canDelete ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarCultura(${cult.id})"><i class="bi bi-trash"></i></button>` : '';
                            
                            html += `
                                <tr>
                                    <td class="fw-bold">${cult.titulo}</td>
                                    <td class="text-muted small">${cult.descripcion}</td>
                                    <td class="text-center">${cult.orden}</td>
                                    <td class="text-center">${badge}</td>
                                    <td class="text-center">${btnEditar}${btnEliminar}</td>
                                </tr>
                            `;
                        });
                    }
                    $('#tbodyCultura').html(html);
                }
            });
        }

        function abrirModalCultura() {
            $('#formCultura')[0].reset();
            $('#cultId').val('');
            $('#modalCulturaLabel').text('Agregar Punto de Cultura');
            $('#cultAuditBox').hide();
            modalCultInstance.show();
        }

        function editarCultura(id) {
            const cult = listadoCultura.find(x => x.id == id);
            if (!cult) return;
            
            $('#cultId').val(cult.id);
            $('#cultTitulo').val(cult.titulo);
            $('#cultDescripcion').val(cult.descripcion);
            $('#cultOrden').val(cult.orden);
            $('#cultActivo').val(cult.activo);

            // Cargar datos de auditoría
            $('#cultAuditCreador').text(`${cult.creador_nombre || 'N/D'} el ${cult.fecha_creacion}`);
            if (cult.usuario_modifica) {
                $('#cultAuditModificador').text(`${cult.modificador_nombre || 'N/D'} el ${cult.fecha_modificacion}`);
                $('#cultAuditModificaRow').show();
            } else {
                $('#cultAuditModificaRow').hide();
            }
            $('#cultAuditBox').show();

            $('#modalCulturaLabel').text('Editar Punto de Cultura');
            modalCultInstance.show();
        }

        function eliminarCultura(id) {
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
                        url: 'ajax/eliminar_cultura.php',
                        method: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(res) {
                            Swal.fire('Eliminado', res.mensaje, 'success');
                            cargarCultura();
                        },
                        error: function(xhr) {
                            let err = xhr.responseJSON ? xhr.responseJSON.error : 'Error al procesar solicitud';
                            Swal.fire('Error', err, 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>

</html>
