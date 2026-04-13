<?php
/* ============================================================
   VISOR DE RECETAS LIGHT — Consulta de Recetas (solo lectura)
   modulos/productos/visor_recetas_light.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('recetario_access_traducido', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Recetas · Pitaya ERP</title>
    <meta name="description" content="Visor compacto de recetas para auditoría rápida de productos">

    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/visor_recetas_light.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Consulta de Recetas'); ?>

            <div class="vrl-layout p-3">


                <!-- ══ Columna izquierda — Menú de productos ══ -->
                <aside class="vrl-menu" id="vrlMenu">
                    <div class="vrl-menu-header">
                        <h6><i class="fas fa-blender me-1"></i> Menú de Productos</h6>
                        <div class="vrl-search-wrap">
                            <i class="fas fa-search vrl-search-icon"></i>
                            <input type="text" class="vrl-search" id="inputBuscar" placeholder="Buscar producto…"
                                autocomplete="off">
                        </div>
                    </div>
                    <div class="vrl-menu-body" id="menuBody">
                        <div class="menu-loading">
                            <div class="spinner-border spinner-border-sm text-success mb-2"></div><br>
                            Cargando productos…
                        </div>
                    </div>
                </aside>

                <!-- ══ Columna derecha — Contenido ══ -->
                <main class="vrl-content" id="vrlContent">

                    <!-- Barra compacta: nombre + grupo + chips de versión -->
                    <div class="vrl-barra-producto d-none" id="barraProducto"></div>

                    <!-- Spinner de carga de receta -->
                    <div class="vrl-spinner" id="spinnerReceta">
                        <div class="spinner-border text-success mb-3" style="width:2rem;height:2rem"></div>
                        <div style="font-size:.88rem">Cargando receta…</div>
                    </div>

                    <!-- Estado vacío inicial -->
                    <div class="vrl-empty" id="panelVacio">
                        <i class="fas fa-blender empty-icon"></i>
                        <p>Elige un grupo y un producto<br>del menú para ver su receta</p>
                    </div>

                    <!-- Tabla de receta -->
                    <div class="vrl-table-wrap d-none" id="panelTabla">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-receta-light" id="tablaReceta">
                                <thead>
                                    <tr class="cols-row">
                                        <th style="width:46px">Orden</th>
                                        <th style="width:52px">Tipo</th>
                                        <th>Insumo Receta</th>
                                        <th style="width:75px;text-align:center">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyReceta"></tbody>
                            </table>
                        </div>
                    </div>

                </main>

            </div><!-- /vrl-layout -->

        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header" style="background:#1a3a2a;color:#fff">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i> Guía — Consulta de Recetas
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold border-bottom pb-2" style="color:#1a3a2a">
                                        <i class="fas fa-mouse-pointer me-2"></i>Cómo navegar (2 clicks)
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <strong>Click 1:</strong> Abre un grupo del menú izquierdo.<br>
                                        <strong>Click 2:</strong> Selecciona un producto — la receta se carga
                                        automáticamente.<br>
                                        Si el producto tiene múltiples versiones, usa los chips de la barra superior
                                        para cambiar entre ellas.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold border-bottom pb-2" style="color:#1a3a2a">
                                        <i class="fas fa-table me-2"></i>Columnas de la tabla
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        <strong>Orden:</strong> Posición en la comanda Access.<br>
                                        <strong>Tipo:</strong> B = Bebida, L = Líquido, P = Polvo.<br>
                                        <strong>Insumo Receta:</strong> Ingrediente mapeado en el nuevo ERP.<br>
                                        <strong>Cantidad y Presentación:</strong> Calculados según unidades y factores
                                        de conversión.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info py-2 px-3 small mb-0">
                                <strong><i class="fas fa-motorcycle me-1"></i> Badge PedidosYa:</strong>
                                Se muestra en versiones cuyo código termina en <code>d</code> (ej:
                                <code>201Gv11d</code>).
                                Gigantona = 20oz · Mediano = 16oz.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/visor_recetas_light.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>