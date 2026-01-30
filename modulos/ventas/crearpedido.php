<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';
require_once '../../includes/funciones.php';

verificarAccesoModulo('atencioncliente');

$usuario = obtenerUsuarioActual();
$edicion = isset($_GET['id']);

// Obtener datos de sucursales
$stmt = $conn->query("SELECT id, nombre FROM sucursales WHERE activa = 1");
$sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos de endulzantes
$stmt = $conn->query("SELECT id, nombre FROM endulzantes WHERE activo = 1");
$endulzantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos de extras
$stmt = $conn->query("SELECT id, nombre, precio FROM extras WHERE activo = 1");
$extras = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener grupos y subgrupos de productos
$grupos = $conn->query("SELECT id, nombre FROM grupos_productos WHERE activo = 1 ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);

$subgrupos = [];
foreach ($grupos as $grupo) {
    $stmt = $conn->prepare("SELECT id, nombre, precio_16oz, precio_20oz, precio_normal 
                           FROM subgrupos_productos 
                           WHERE grupo_id = ? AND activo = 1 
                           ORDER BY orden");
    $stmt->execute([$grupo['id']]);
    $subgrupos[$grupo['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener productos
$productos = [];
foreach ($subgrupos as $grupo_id => $subgrupos_grupo) {
    foreach ($subgrupos_grupo as $subgrupo) {
        $stmt = $conn->prepare("SELECT id, nombre, nombre_factura, tiene_tamanos, precio_16oz, precio_20oz, precio_fijo 
                               FROM productos_delivery 
                               WHERE subgrupo_id = ? AND activo = 1
                               ORDER BY nombre_factura");
        $stmt->execute([$subgrupo['id']]);
        $productos[$subgrupo['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Obtener tipo de cambio actual
$tipo_cambio = $conn->query("SELECT tasa FROM tipo_cambio ORDER BY fecha DESC LIMIT 1")->fetchColumn();

// Si es edición, cargar datos del pedido
$pedido = null;
$detalles = [];
if ($edicion) {
    $pedido_id = $_GET['id'];
    
    // Obtener datos del pedido
    $stmt = $conn->prepare("SELECT v.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono, 
                           c.direccion as cliente_direccion, c.codigo as cliente_codigo
                           FROM ventas v
                           LEFT JOIN clientes c ON v.cliente_id = c.id
                           WHERE v.id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles del pedido
    $stmt = $conn->prepare("SELECT vd.*, p.nombre as producto_nombre
                           FROM ventas_detalle vd
                           JOIN productos_delivery p ON vd.producto_id = p.id
                           WHERE vd.venta_id = ?");
    $stmt->execute([$pedido_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener extras de cada detalle
    foreach ($detalles as &$detalle) {
        $stmt = $conn->prepare("SELECT e.id, e.nombre, e.precio
                       FROM ventas_extras ve
                       JOIN extras e ON ve.extra_id = e.id
                       WHERE ve.venta_detalle_id = ?");
        $stmt->execute([$detalle['id']]);
        $detalle['extras'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($detalle);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edicion ? 'Editar Pedido ' . htmlspecialchars($pedido['codigo']) : 'Nuevo Pedido' ?> | Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!--<link rel="stylesheet" href="ventas.css">-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/png" href="../../assets/img/icon12.png">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
</head>
<link rel="stylesheet" href="stylescrearp.css">
<body>
    <div class="container">
        <header class="header-ventas">
            <h1>
                <a href="index.php" class="btn-volver" style="display:none;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <?= $edicion ? 'Editar Pedido ' . htmlspecialchars($pedido['codigo']) : 'Nuevo Pedido' ?>
            </h1>
            <div class="header-info">
                <div class="info-pedido">
                    <span id="fecha-hora"><?= date('d-M-y h:i a') ?></span>
                    <span style="display:none;" id="codigo-pedido"><?= $edicion ? htmlspecialchars($pedido['id']) : ($conn->query("SELECT MAX(id) + 1 FROM ventas")->fetchColumn() ?? 1) ?></span>
                </div>
                <div class="user-info">
                    <span><?= htmlspecialchars($usuario['nombre']) ?></span>
                    <small><?= ucfirst($usuario['rol']) ?></small>
                </div>
            </div>
        </header>

        <form id="form-pedido" action="procesar_pedido.php" method="post">
            <input type="hidden" name="pedido_id" value="<?= $edicion ? $pedido['id'] : '' ?>">
            
            <div class="secciones-superiores">
                <div class="seccion-cliente">
                    <div class="seccion">
                        <div class="seccion-header">
                            <h2><i class="fas fa-user"></i> Cliente</h2>
                        </div>
                            
                        <div class="seccion-body">
                            <div class="form-group">
                                <label for="sucursal">Sucursal</label>
                                <select id="sucursal" name="sucursal_id" required>
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <option value="<?= $sucursal['id'] ?>" 
                                            <?= ($edicion && $pedido['sucursal_id'] == $sucursal['id']) || (!$edicion && $usuario['sucursal_id'] == $sucursal['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                                
                            <div class="form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="text" id="telefono" name="telefono" 
                                       value="<?= $edicion ? htmlspecialchars($pedido['cliente_telefono'] ?? '') : '' ?>" 
                                       placeholder="Ingrese teléfono">
                            </div>
                                
                            <div class="form-group">
                                <label for="codigo-club">Código Club Pitaya</label>
                                <input type="text" id="codigo-club" name="codigo_club" 
                                       value="<?= $edicion && !empty($pedido['cliente_codigo']) ? htmlspecialchars($pedido['cliente_codigo']) : '0' ?>" 
                                       placeholder="0 si no es miembro" 
                                       <?= ($edicion && !empty($pedido['cliente_codigo']) && $pedido['cliente_codigo'] != '0') ? 'readonly' : '' ?>>
                            </div>
                                
                            <div class="form-group">
                                <label for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" 
                                       value="<?= $edicion ? htmlspecialchars($pedido['cliente_nombre'] ?? '') : '' ?>" 
                                       placeholder="Nombre del cliente" <?= $edicion && !empty($pedido['cliente_id']) ? 'readonly' : '' ?>>
                            </div>
                                
                            <div class="form-group">
                                <label for="direccion">Dirección</label>
                                <input type="text" id="direccion" name="direccion" 
                                       value="<?= $edicion ? htmlspecialchars($pedido['cliente_direccion'] ?? '') : '' ?>" 
                                       placeholder="Dirección del cliente">
                            </div>
                            
                            <div class="form-group">
                                <label for="indicaciones">Indicaciones Especiales</label>
                                <textarea id="indicaciones" name="indicaciones" placeholder="Ej: Envase con dedicatoria, etc." 
                                          style="background-color: <?= empty($pedido['notas']) ? '#fff' : '#bfd113' ?>; 
                                                 font-weight: <?= empty($pedido['notas']) ? 'normal' : 'bold' ?>"><?= $edicion ? htmlspecialchars($pedido['notas'] ?? '') : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="seccion-servicio">
                    <div class="seccion">
                        <div class="seccion-header">
                            <h2><i class="fas fa-truck"></i> Servicio</h2>
                        </div>
                        <div class="seccion-body">
                            <div class="form-group">
                                <label for="tipo-servicio">Tipo de Servicio</label>
                                <select id="tipo-servicio" name="tipo_servicio" required>
                                    <option value="delivery" <?= $edicion && $pedido['tipo_servicio'] == 'delivery' ? 'selected' : '' ?>>Delivery</option>
                                    <option value="retiro_local" <?= $edicion && $pedido['tipo_servicio'] == 'retiro_local' ? 'selected' : '' ?>>Retiro en Local</option>
                                </select>
                            </div>
                            
                            <div id="delivery-options" class="<?= $edicion && $pedido['tipo_servicio'] == 'delivery' ? '' : 'hidden' ?>">
                                <div class="form-group">
                                    <label for="distancia">Distancia (KM)</label>
                                    <input type="number" id="distancia" name="distancia" step="0.1" min="0"
                                           value="<?= $edicion ? htmlspecialchars($pedido['distancia'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="empresa-delivery">Empresa Delivery</label>
                                    <select id="empresa-delivery" name="empresa_delivery" class="hidden">
                                        <option value="">Seleccione una empresa</option>
                                        <!-- Las opciones se llenarán con JavaScript -->
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="conductor">Conductor</label>
                                    <input type="text" id="conductor" name="conductor" 
                                           value="<?= $edicion ? htmlspecialchars($pedido['conductor'] ?? '') : '' ?>" 
                                           placeholder="Nombre del conductor">
                                </div>
                                
                                <div class="form-group">
                                    <label for="cargo-delivery">Cargo Delivery</label>
                                    <input type="number" id="cargo-delivery" name="cargo_delivery" step="0.01" min="0"
                                           value="<?= $edicion ? htmlspecialchars($pedido['cargo_delivery'] ?? '') : '' ?>">
                                </div>
                            </div>
                            
                            <div id="retiro-local" class="<?= $edicion && $pedido['tipo_servicio'] == 'retiro_local' ? '' : 'hidden' ?>">
                                <div class="form-group">
                                    <label for="hora-retiro">Hora de Retiro</label>
                                    <input type="time" id="hora-retiro" name="hora_retiro" 
                                           value="<?= $edicion ? htmlspecialchars($pedido['hora_retiro'] ?? '') : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="seccion">
                <div class="seccion-header">
                    <h2><i class="fas fa-utensils"></i> Productos</h2>
                </div>
                <div class="seccion-body">
                    <!-- Modificar el contenedor de categorías para mejorar el layout -->
                    <div class="categorias-productos">
                        <!-- Columna de grupos -->
                        <div class="grupos-container">
                            <div class="grupos">
                                <?php foreach ($grupos as $grupo): ?>
                                    <button type="button" class="btn-grupo" data-grupo="<?= $grupo['id'] ?>">
                                        <i class="fas fa-chevron-right"></i>
                                        <?= htmlspecialchars($grupo['nombre']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Contenedor de subgrupos (columnas) -->
                        <?php foreach ($grupos as $grupo): ?>
                            <div class="subgrupos-container" data-grupo="<?= $grupo['id'] ?>">
                                <?php if (isset($subgrupos[$grupo['id']])): ?>
                                    <?php foreach ($subgrupos[$grupo['id']] as $subgrupo): ?>
                                        <div class="subgrupo-columna">
                                            <h3>
                                                <?= htmlspecialchars($subgrupo['nombre']) ?>
                                                <!-- Mostrar precios por tamaño solo para el grupo de Batidos (ID 1) -->
                                                <?php if ($grupo['id'] == 1 && $subgrupo['precio_16oz'] && $subgrupo['precio_20oz']): ?>
                                                    <div class="precios-subgrupo">
                                                        <span>16oz: C$<?= $subgrupo['precio_16oz'] ?></span>
                                                        <span>20oz: C$<?= $subgrupo['precio_20oz'] ?></span>
                                                    </div>
                                                <?php elseif ($subgrupo['precio_normal']): ?>
                                                    <div class="precio-subgrupo">C$<?= $subgrupo['precio_normal'] ?></div>
                                                <?php endif; ?>
                                            </h3>
                                            <div class="productos-grid">
                                                <?php if (isset($productos[$subgrupo['id']])): ?>
                                                    <?php foreach ($productos[$subgrupo['id']] as $producto): ?>
                                                        <button type="button" class="btn-producto" data-producto="<?= $producto['id'] ?>">
                                                            <?= htmlspecialchars($producto['nombre']) ?>
                                                            <!-- Mostrar precio fijo solo si no es grupo de Batidos -->
                                                            <?php if ($grupo['id'] != 1 && $producto['precio_fijo']): ?>
                                                                <span class="precio-fijo">C$<?= $producto['precio_fijo'] ?></span>
                                                            <?php endif; ?>
                                                        </button>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="tabla-pedido">
                        <table id="tabla-productos">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Tamaño</th>
                                    <th>Cantidad</th>
                                    <th>P.U.</th>
                                    <th>Endulzante</th>
                                    <th>Extras</th>
                                    <th>Promoción</th>
                                    <th>Notas</th> <!-- Nueva columna -->
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($edicion && count($detalles) > 0): ?>
                                    <?php foreach ($detalles as $detalle): ?>
                                        <tr data-producto="<?= $detalle['producto_id'] ?>">
                                            <input type="hidden" name="producto_id[]" value="<?= $detalle['producto_id'] ?>">
                                            <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                                            <td>
                                                <select name="tamano[]" class="tamano">
                                                    <option value="16oz" <?= $detalle['tamano'] == '16oz' ? 'selected' : '' ?>>16oz</option>
                                                    <option value="20oz" <?= $detalle['tamano'] == '20oz' ? 'selected' : '' ?>>20oz</option>
                                                    <option value="unico" <?= $detalle['tamano'] == 'unico' ? 'selected' : '' ?>></option>
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button" class="btn-cantidad" data-action="decrement">-</button>
                                                <input type="number" name="cantidad[]" class="cantidad" value="<?= $detalle['cantidad'] ?>" min="1">
                                                <button type="button" class="btn-cantidad" data-action="increment">+</button>
                                            </td>
                                            <td class="precio">C$ <?= number_format($detalle['precio_unitario'], 0) ?></td>
                                            <td>
                                                <div class="endulzante-container">
                                                    <input type="text" class="endulzante-input" placeholder="Buscar endulzante..." data-producto="${productoId}">
                                                    <button type="button" class="btn-endulzante-dropdown">
                                                        <i class="fas fa-chevron-down"></i>
                                                    </button>
                                                    <div class="endulzante-dropdown hidden">
                                                        <?php foreach ($endulzantes as $endulzante): ?>
                                                            <div class="endulzante-option" data-id="<?= $endulzante['id'] ?>" data-nombre="<?= htmlspecialchars($endulzante['nombre']) ?>">
                                                                <?= htmlspecialchars($endulzante['nombre']) ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <input type="hidden" name="endulzante[]" class="endulzante-id" value=""> <!-- Valor por defecto: el primer endulzante disponible -->
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn-agregar-extra">+ Extra</button>
                                                <div class="extras-list">
                                                    <?php foreach ($detalle['extras'] as $extra): ?>
                                                        <div class="extra-item">
                                                            <span><?= htmlspecialchars($extra['nombre']) ?></span>
                                                            <button type="button" class="btn-eliminar-extra">×</button>
                                                            <input type="hidden" name="extras[<?= $detalle['producto_id'] ?>][]" value="<?= $extra['id'] ?>">
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <select name="promocion[]" class="promocion" data-producto="${productoId}">
                                                    <option value="">Ninguna</option>
                                                    <!-- Las opciones se llenarán con JavaScript -->
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="notas_producto[]" class="notas-producto" 
                                                       value="<?= htmlspecialchars($detalle['notas'] ?? '') ?>" 
                                                       placeholder="Ej: Sin hielo">
                                            </td>
                                            <td>
                                                <button type="button" class="btn-eliminar-producto">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="empty-row">
                                        <td colspan="8">No hay productos agregados</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="seccion">
                <div class="seccion-header">
                    <h2><i class="fas fa-money-bill-wave"></i> Pago</h2>
                </div>
                <div class="seccion-body">
                    <div class="resumen-pago">
                        <div class="totales">
                            <div class="total-item">
                                <span>Subtotal:</span>
                                <span id="subtotal">C$ 0</span>
                            </div>
                            <div class="total-item">
                                <span>Delivery:</span>
                                <span id="delivery-total">C$ 0</span>
                            </div>
                            <div class="total-item total-final">
                                <span>Total:</span>
                                <span id="total">C$ 0</span>
                            </div>
                            <div class="total-item">
                                <small style="visibility:hidden;">Tipo de cambio: <span id="tipo-cambio"><?= number_format($tipo_cambio, 2) ?></span></small>
                                <small>Total en dólares: <span id="total-dolares">$ 0.0</span><span id="tipo-cambio"> (<?= number_format($tipo_cambio, 1) ?>)</span></small>
                            </div>
                        </div>
                        
                        <div class="metodo-pago">
                            <div class="form-group">
                                <label for="metodo-pago">Método de Pago</label>
                                <select id="metodo-pago" name="metodo_pago" required>
                                    <option value="transferencia" <?= $edicion && $pedido['tipo_pago'] == 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                                    <option value="efectivo" <?= $edicion && $pedido['tipo_pago'] == 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                    <option value="pos" <?= $edicion && $pedido['tipo_pago'] == 'pos' ? 'selected' : '' ?>>POS</option>
                                </select>
                            </div>
                            
                            <div id="efectivo-options" class="<?= $edicion && $pedido['tipo_pago'] == 'efectivo' ? '' : 'hidden' ?>">
                                <div class="form-group">
                                    <label for="pago-cordobas">Pago Recibido (C$)</label>
                                    <input type="number" id="pago-cordobas" name="pago_cordobas" step="0.01" min="0"
                                           value="<?= $edicion ? htmlspecialchars($pedido['pago_recibido_cordobas'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="pago-dolares">Pago Recibido ($)</label>
                                    <input type="number" id="pago-dolares" name="pago_dolares" step="0.01" min="0"
                                           value="<?= $edicion ? htmlspecialchars($pedido['pago_recibido_dolares'] ?? '') : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="cambio">Cambio (C$)</label>
                                    <input type="number" id="cambio" name="cambio" step="0.01" min="0" readonly
                                           value="<?= $edicion ? htmlspecialchars($pedido['cambio_cordobas'] ?? '') : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="acciones-pedido">
                <button type="button" id="btn-cancelar" class="btn-cancelar">
                    <i class="fas fa-times"></i>
                </button>
                <button type="submit" id="btn-guardar" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar y Capturar
                </button>
            </div>
        </form>
    </div>
    
    <!-- Modal para seleccionar extras -->
    <div id="modal-extras" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Agregar Extras</h3>
                <button type="button" class="btn-cerrar-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="extras-disponibles">
                    <?php foreach ($extras as $extra): ?>
                        <div class="extra-item" data-extra-id="<?= $extra['id'] ?>">
                            <span><?= htmlspecialchars($extra['nombre']) ?> (C$<?= ($extra['precio']) ?>)</span>
                            <button type="button" class="btn-agregar-extra-modal">Agregar</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/jquery.min.js"></script>
    <!--<script src="ventas.js"></script>-->
    <script>
        // Prevenir envío del formulario con Enter
        $(document).on('keypress', 'input, select, textarea', function(e) {
            if (e.which === 13) { // 13 es el código de Enter
                e.preventDefault();
                return false;
            }
        });
        
        //Cargar el cambio como confirmación de la sucursal con el id para los campos Distancia y Conductor
        $(document).ready(function() {
            if ($('#sucursal').val()) {
                $('#sucursal').trigger('change');
            }
            
            // Resaltar precios al pasar el mouse (solo para grupo Batidos)
            $(document).on('mouseenter', '[data-grupo="1"] .precios-subgrupo span', function() {
                $(this).css({
                    'color': '#0E544C',
                    'font-weight': 'bold'
                });
            }).on('mouseleave', '[data-grupo="1"] .precios-subgrupo span', function() {
                $(this).css({
                    'color': '#666',
                    'font-weight': 'normal'
                });
            });
            
            // Mostrar campos de delivery inmediatamente si está seleccionado
            if ($('#tipo-servicio').val() === 'delivery') {
                $('#delivery-options').removeClass('hidden');
            }
            
            // Mostrar campos de efectivo inmediatamente si está seleccionado
            if ($('#metodo-pago').val() === 'efectivo') {
                $('#efectivo-options').removeClass('hidden');
            }
            
            // Mostrar información de transferencia si está seleccionado
            if ($('#metodo-pago').val() === 'transferencia') {
                mostrarInfoTransferencia();
            }
            
            // Modificar el cambio de método de pago
            $('#metodo-pago').change(function() {
                let metodo = $(this).val();
                
                $('#efectivo-options, #transferencia-info').addClass('hidden');
                
                if (metodo === 'efectivo') {
                    $('#efectivo-options').removeClass('hidden');
                } else if (metodo === 'transferencia') {
                    mostrarInfoTransferencia();
                }
            });
            
            // Confirmar antes de cerrar la pestaña
            window.addEventListener("beforeunload", function (e) {
                e.preventDefault(); // Necesario para algunas versiones de navegador
                e.returnValue = ''; // Este valor activa el mensaje
            });
        });
        
        function mostrarInfoTransferencia() {
            // Eliminar info anterior si existe
            $('#transferencia-info').remove();
            
            // Crear contenedor con la información bancaria
            let info = $(`
                <div id="transferencia-info" style="margin-top: 5px; padding: 3px; background: #f5f5f5; border-radius: 5px;">
                    <h4 style="margin-bottom: 2px; color: #0E544C; display:none;">Información para Transferencia</h4>
                    <img src="../../assets/img/bancos_pagopitaya.png" alt="Datos Bancarios" style="max-width: 100%; height: auto; border: 1px solid #ddd;">
                    <p style="margin-top: 5px; font-size: 0.9em;">
                        <strong>Nota:</strong> Por favor enviar comprobante de transferencia al WhatsApp de Batidos Pitaya.
                    </p>
                </div>
            `);
            
            $('#metodo-pago').after(info);
        }
    </script>
    <script>
        $(document).ready(function() {
            // Verificar si hay productos al cargar
            if ($('#tabla-productos tbody tr[data-producto]').length > 0) {
                $('.empty-row').remove();
            }
            
            // Variables globales
            let tipoCambio = parseFloat(<?= number_format($tipo_cambio, 1) ?>); // Obtiene el valor ya formateado desde PHP
            $('#tipo-cambio').text(tipoCambio.toFixed(1)); // Asegura formato inicial
            
            let productosAgregados = [];
            
            // Si estamos editando un pedido, cargar los productos
            if ($('#codigo-pedido').text().startsWith('P-')) {
                $('tr[data-producto]').each(function() {
                    let productoId = $(this).data('producto');
                    let cantidad = parseInt($(this).find('.cantidad').val());
                    let precio = parseFloat($(this).find('.precio').text().replace('C$ ', ''));
                    
                    productosAgregados.push({
                        id: productoId,
                        cantidad: cantidad,
                        precio: precio
                    });
                });
            }
            
            // Función para buscar promociones aplicables a un producto
function buscarPromociones(productoId) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'buscar_promociones.php',
            method: 'POST',
            data: { producto_id: productoId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    resolve(response.promociones);
                } else {
                    reject(response.error);
                }
            },
            error: function(xhr, status, error) {
                reject(error);
            }
        });
    });
}
            
            // Actualizar fecha y hora cada minuto
            function actualizarFechaHora() {
                let ahora = new Date();
                let opcionesFecha = { day: '2-digit', month: 'short', year: '2-digit' };
                let opcionesHora = { hour: '2-digit', minute: '2-digit', hour12: true };
                
                let fecha = ahora.toLocaleDateString('es-ES', opcionesFecha);
                let hora = ahora.toLocaleTimeString('es-ES', opcionesHora);
                
                $('#fecha-hora').text(`${fecha} ${hora}`);
            }
            
            setInterval(actualizarFechaHora, 60000);
            actualizarFechaHora();
            
            // Manejar cambio de tipo de servicio
            $('#tipo-servicio').change(function() {
                let tipo = $(this).val();
                
                $('#delivery-options, #retiro-local').addClass('hidden');
                
                if (tipo === 'delivery') {
                    $('#delivery-options').removeClass('hidden');
                    $('#retiro-local input').val(''); // Limpiar campos de retiro
                } else if (tipo === 'retiro_local') {
                    $('#retiro-local').removeClass('hidden');
                    // Limpiar campos de delivery
                    $('#empresa-delivery, #conductor').val('');
                    $('#distancia, #cargo-delivery').val('0');
                }
                
                calcularTotales();
            });
            
            // Manejar cambio de empresa delivery para cargar cargos, cambio dinámico cada vez que cambia la sucursal y la empresa delivery
            $(document).on('change', '#empresa-delivery', function() {
                let empresaId = $(this).val();
                let distancia = parseFloat($('#distancia').val()) || 0;
                
                if (!empresaId) {
                    // Si no hay empresa seleccionada, mostrar campo vacío editable
                    $('#cargo-delivery-select').replaceWith('<input type="number" id="cargo-delivery" name="cargo_delivery" step="0.01" min="0" value="0">');
                    calcularTotales();
                    return;
                }
                
                // Mostrar loader durante la búsqueda
                let cargoField = $('#cargo-delivery-select').length ? $('#cargo-delivery-select') : $('#cargo-delivery');
                cargoField.after('<div id="loading-cargo" style="color: #666; font-size: 0.8em;">Cargando tarifas...</div>');
                
                $.ajax({
                    url: 'buscar_cargos_delivery.php',
                    method: 'POST',
                    data: { empresa_id: empresaId, distancia: distancia },
                    dataType: 'json',
                    success: function(response) {
                        $('#loading-cargo').remove();
                        
                        if (response.success) {
                            if (response.cargos.length === 0) {
                                // Si no hay cargos definidos, mostrar campo editable
                                $('#cargo-delivery-select').replaceWith('<input type="number" id="cargo-delivery" name="cargo_delivery" step="0.01" min="0" value="0">');
                            } else if (response.cargos.length === 1) {
                                // Si solo hay un cargo, mostrarlo como campo fijo
                                $('#cargo-delivery').replaceWith(
                                    `<input type="number" id="cargo-delivery" name="cargo_delivery" step="0.01" min="0" 
                                     value="${response.cargos[0].valor}"
                                     title="${response.cargos[0].nombre}${response.cargos[0].descripcion ? ' - ' + response.cargos[0].descripcion : ''}">`
                                );
                            } else {
                                // Si hay múltiples cargos, mostrar dropdown
                                let select = $('<select id="cargo-delivery-select" name="cargo_delivery" class="form-control"></select>');
                                
                                response.cargos.forEach(cargo => {
                                    let optionText = `${cargo.nombre}: C$${cargo.valor}`;
                                    if (cargo.descripcion) {
                                        optionText += ` (${cargo.descripcion})`;
                                    }
                                    select.append(`<option value="${cargo.valor}">${optionText}</option>`);
                                });
                                
                                $('#cargo-delivery').replaceWith(select);
                                
                                // Seleccionar automáticamente el cargo regular si existe
                                let cargoRegular = response.cargos.find(c => c.nombre.toLowerCase().includes('regular'));
                                if (cargoRegular) {
                                    select.val(cargoRegular.valor);
                                }
                            }
                            
                            // Calcular totales cuando cambie el cargo
                            $('#cargo-delivery, #cargo-delivery-select').off('change').on('change', calcularTotales);
                            calcularTotales();
                        }
                    },
                    error: function() {
                        $('#loading-cargo').remove();
                        // En caso de error, mostrar campo editable
                        $('#cargo-delivery-select').replaceWith('<input type="number" id="cargo-delivery" name="cargo_delivery" step="0.01" min="0" value="0">');
                    }
                });
            });
            
            // También necesitamos manejar cambios en la distancia para actualizar el cargo
            $(document).on('change', '#distancia', function() {
                // Solo actualizar si ya hay una empresa seleccionada
                if ($('#empresa-delivery').val()) {
                    $('#empresa-delivery').trigger('change');
                }
            });
            
            // Llamar al cambio inicial si estamos editando un pedido con empresa delivery
            <?php if ($edicion && $pedido['tipo_servicio'] == 'delivery' && $pedido['servicio_delivery_id']): ?>
            $(document).ready(function() {
                $('#empresa-delivery').val(<?= $pedido['servicio_delivery_id'] ?>).trigger('change');
                <?php if ($pedido['cargo_delivery']): ?>
                setTimeout(function() {
                    $('#cargo-delivery-select').val(<?= $pedido['cargo_delivery'] ?>);
                }, 200);
                <?php endif; ?>
            });
            <?php endif; ?>
            
            // Manejar cambio de método de pago
            $('#metodo-pago').change(function() {
                let metodo = $(this).val();
                
                $('#efectivo-options').addClass('hidden');
                
                if (metodo === 'efectivo') {
                    $('#efectivo-options').removeClass('hidden');
                }
            });
            
            // Calcular cambio cuando se ingresa pago
            $('#pago-cordobas, #pago-dolares').on('input', function() {
                calcularCambio();
            });
            
            function calcularCambio() {
                let total = parseFloat($('#total').text().replace('C$ ', '')) || 0;
                let pagoCordobas = parseFloat($('#pago-cordobas').val()) || 0;
                let pagoDolares = parseFloat($('#pago-dolares').val()) || 0;
                
                let totalPago = pagoCordobas + (pagoDolares * tipoCambio);
                let cambio = totalPago - total;
                
                $('#cambio').val(cambio > 0 ? Math.round(cambio) : '0');
            }
            
            // Función para grupos
            // Mostrar/ocultar columnas de subgrupos al hacer clic en grupo
            $(document).on('click', '.btn-grupo', function() {
                const grupoId = $(this).data('grupo');
                
                // Si ya está activo, retraer
                if ($(this).hasClass('active')) {
                    $(this).removeClass('active').find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    $('.subgrupos-container').removeClass('active');
                    return;
                }
                
                // Desactivar todos los grupos
                $('.btn-grupo').removeClass('active').find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                
                // Ocultar todos los contenedores de subgrupos
                $('.subgrupos-container').removeClass('active');
                
                // Activar este grupo
                $(this).addClass('active').find('i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
                
                // Mostrar los subgrupos de este grupo
                $(`.subgrupos-container[data-grupo="${grupoId}"]`).addClass('active');
            });
            
            // Mostrar el primer grupo por defecto
            <?php if (!$edicion): ?>
            $('.btn-grupo:first').trigger('click');
            <?php endif; ?>
            
            // Función para retraer grupos al hacer clic nuevamente
            $(document).on('click', '.btn-grupo.active', function() {
                $(this).removeClass('active').find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                $('.subgrupos-productos').hide();
            });
            
            // Función para subgrupos
            $(document).on('click', '.btn-subgrupo', function(e) {
                e.stopPropagation(); // Importante!
                const subgrupoId = $(this).data('subgrupo');
                const $productos = $(`.productos-subgrupo[data-subgrupo="${subgrupoId}"]`);
                
                // Contraer otros productos
                $('.productos-subgrupo').not($productos).slideUp(200);
                $('.btn-subgrupo').not($(this)).removeClass('active');
                $('.btn-subgrupo').not($(this)).find('i').removeClass('fa-chevron-down').addClass('fa-chevron-right');
                
                // Toggle estado actual
                $(this).toggleClass('active');
                $(this).find('i').toggleClass('fa-chevron-right fa-chevron-down');
                $productos.slideToggle(200);
                
                // Mostrar contenedor
                $('#productos-container').removeClass('hidden');
            });
            
            // Manejar endulzantes
            $(document).on('click', '.btn-endulzante-dropdown', function() {
                $(this).siblings('.endulzante-dropdown').toggleClass('hidden');
            });
            
            $(document).on('click', '.endulzante-option', function() {
                let container = $(this).closest('.endulzante-container');
                let nombre = $(this).data('nombre');
                let id = $(this).data('id');
                
                container.find('.endulzante-input').val(nombre);
                container.find('.endulzante-id').val(id);
                container.find('.endulzante-dropdown').addClass('hidden');
            });
            
            $(document).on('input', '.endulzante-input', function() {
                let searchTerm = $(this).val().toLowerCase();
                let dropdown = $(this).siblings('.endulzante-dropdown');
                
                dropdown.find('.endulzante-option').each(function() {
                    let text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(searchTerm));
                });
            });
            
            // Cerrar dropdown al hacer clic fuera
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.endulzante-container').length) {
                    $('.endulzante-dropdown').addClass('hidden');
                }
            });
                
            // Agregar producto al pedido
            $(document).on('click', '.btn-producto', function() {
                let productoId = $(this).data('producto');
                
                // Obtener información del producto y endulzantes disponibles
                $.when(
                    $.ajax({
                        url: 'buscar_producto.php',
                        method: 'POST',
                        data: { id: productoId },
                        dataType: 'json'
                    }),
                    $.ajax({
                        url: 'buscar_endulzantes.php',
                        method: 'POST',
                        data: { producto_id: productoId },
                        dataType: 'json'
                    })
                ).done(function(productoResponse, endulzantesResponse) {
                    if (productoResponse[0].success && endulzantesResponse[0].success) {
                        let producto = productoResponse[0].producto;
                        let endulzantes = endulzantesResponse[0].endulzantes;
                        let precio = producto.precio_20oz || producto.precio_fijo;
                        let tieneTamanos = producto.tiene_tamanos;
                        
                        // Siempre agregar nueva fila con nombre_factura
                        agregarFilaProducto(
                            productoId, 
                            producto.nombre_factura || $(this).text().trim(), // Usar nombre_factura si existe
                            tieneTamanos, 
                            precio, 
                            endulzantes
                        );
                        
                        // Actualizar totales
                        calcularTotales();
                        
                        // Ocultar fila vacía si existe
                        $('.empty-row').remove();
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Error detallado:', xhr.responseText, status, error);
                    alert('Error al cargar el producto. Detalles en consola (F12).');
                });
            });
            
            // En la función agregarFilaProducto(), asegúrate de incluir todos los campos necesarios:
            async function agregarFilaProducto(productoId, productoNombre, tieneTamanos, precio, endulzantes) {
                // Eliminar fila vacía si existe
                $('.empty-row').remove();
                
                // Obtener promociones para este producto
                let promociones = [];
                try {
                    promociones = await buscarPromociones(productoId);
                } catch (error) {
                    console.error('Error al cargar promociones:', error);
                }
                
                // Generar opciones de endulzantes
                let endulzantesHtml = '';
                endulzantes.forEach(endulzante => {
                    let opcionesHtml = '';
                    
                    if (endulzante.opciones && endulzante.opciones.length > 0) {
                        endulzante.opciones.forEach(opcion => {
                            opcionesHtml += `<option value="${opcion.valor}">${endulzante.nombre} ${opcion.texto}</option>`;
                        });
                    } else {
                        opcionesHtml += `<option value="${endulzante.id}">${endulzante.nombre}</option>`;
                    }
                    
                    endulzantesHtml += opcionesHtml;
                });
                
                // Generar opciones de promociones
                let promocionesHtml = '<option value="">Ninguna</option>';
                promociones.forEach(promo => {
                    promocionesHtml += `<option value="${promo.id}" title="${promo.nombre}">${promo.nombre} (${promo.descuento})</option>`;
                });
                
                // Crear ID único para esta instancia del producto
                let instanciaId = 'prod_' + productoId + '_' + Date.now();
                
                let fila = `
                    <tr data-producto="${productoId}" data-instancia="${instanciaId}">
                        <input type="hidden" name="producto_id[]" value="${productoId}">
                        <td>${productoNombre}</td>
                        <td>
                            <select name="tamano[]" class="tamano" ${!tieneTamanos ? 'disabled' : ''}>
                                ${tieneTamanos ? `
                                    <option value="16oz">16oz</option>
                                    <option value="20oz" selected>20oz</option>
                                ` : `
                                    <option value="unico" selected></option>
                                `}
                            </select>
                            ${!tieneTamanos ? '<input type="hidden" name="tamano[]" value="unico">' : ''}
                        </td>
                        <td class="cantidad-container">
                            <button type="button" class="btn-cantidad" data-action="decrement">-</button>
                            <input type="number" name="cantidad[]" class="cantidad" value="1" min="1">
                            <button type="button" class="btn-cantidad" data-action="increment">+</button>
                        </td>
                        <td class="precio">C$ ${precio.toFixed(0)}</td>
                        <td>
                            <select name="endulzante[${instanciaId}]" class="endulzante">
                                ${endulzantesHtml}
                            </select>
                        </td>
                        <td class="extras-container">
                            <button type="button" class="btn-agregar-extra">+ Extra</button>
                            <div class="extras-list"></div>
                        </td>
                        <td>
                            <select name="promocion[]" class="promocion" data-producto="${productoId}">
                                ${promocionesHtml}
                            </select>
                        </td>
                        <td>
                            <input type="text" name="notas_producto[]" class="notas-producto" placeholder="Ej: Sin hielo">
                        </td>
                        <td>
                            <button type="button" class="btn-eliminar-producto">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                
                $('#tabla-productos tbody').append(fila);
                
                calcularTotales();
                
                // Actualizar la variable de productos agregados con ID único
                productosAgregados.push({
                    id: productoId,
                    instanciaId: instanciaId,
                    cantidad: 1,
                    precio: precio,
                    promocion_id: null
                });
            }
            
            // Manejar cambio de cantidad
            $(document).on('click', '.btn-cantidad', function() {
                let action = $(this).data('action');
                let input = $(this).siblings('.cantidad');
                let value = parseInt(input.val());
                
                if (action === 'increment') {
                    input.val(value + 1);
                } else if (action === 'decrement' && value > 1) {
                    input.val(value - 1);
                }
                
                // Actualizar cantidad en el array
                let productoId = $(this).closest('tr').data('producto');
                let producto = productosAgregados.find(p => p.id == productoId);
                if (producto) {
                    producto.cantidad = parseInt(input.val());
                }
                
                calcularTotales();
            });
            
            // Manejar cambio de promoción
            $(document).on('change', '.promocion', function() {
                let promocionId = $(this).val();
                let instanciaId = $(this).closest('tr').data('instancia');
                let productoId = $(this).data('producto');
                let fila = $(this).closest('tr');
                let selectTamano = fila.find('.tamano');
                let tamano = selectTamano.val();
                
                // Primero obtener el precio base según el tamaño seleccionado
                $.ajax({
                    url: 'buscar_producto.php',
                    method: 'POST',
                    data: { id: productoId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let producto = response.producto;
                            let precioBase = 0;
                            
                            if (tamano === '16oz' && producto.precio_16oz) {
                                precioBase = producto.precio_16oz;
                            } else if (tamano === '20oz' && producto.precio_20oz) {
                                precioBase = producto.precio_20oz;
                            } else if (producto.precio_fijo) {
                                precioBase = producto.precio_fijo;
                            }
                            
                            // Si no hay promoción seleccionada, usar precio base
                            if (!promocionId) {
                                fila.find('.precio').text('C$ ' + precioBase.toFixed(0));
                                
                                // Actualizar en el array de productos
                                let producto = productosAgregados.find(p => p.id == productoId);
                                if (producto) {
                                    producto.precio = precioBase;
                                    producto.promocion_id = null;
                                }
                                
                                calcularTotales();
                                return;
                            }
                            
                            // Si hay promoción, aplicar descuento
                            $.ajax({
                                url: 'aplicar_promocion.php',
                                method: 'POST',
                                data: {
                                    promocion_id: promocionId,
                                    producto_id: productoId,
                                    venta_id: $('#codigo-pedido').text().replace('P-', '')
                                },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.success) {
                                        // Actualizar precio en la fila
                                        fila.find('.precio').text('C$ ' + response.nuevo_precio.toFixed(0));
                                        
                                        // Actualizar en el array de productos
                                        let producto = productosAgregados.find(p => p.id == productoId);
                                        if (producto) {
                                            producto.precio = response.nuevo_precio;
                                            producto.promocion_id = promocionId;
                                        }
                                        
                                        calcularTotales();
                                    }
                                }
                            });
                        }
                    }
                });
            });
            
            $(document).on('change', '.cantidad', function() {
                let value = parseInt($(this).val());
                if (isNaN(value) || value < 1) {
                    $(this).val(1);
                }
                
                // Actualizar cantidad en el array
                let productoId = $(this).closest('tr').data('producto');
                let producto = productosAgregados.find(p => p.id == productoId);
                if (producto) {
                    producto.cantidad = parseInt($(this).val());
                }
                
                calcularTotales();
            });
            
            // Manejar cambio de tamaño
            // Cambiar el evento de cambio de tamaño
            $(document).on('change', '.tamano:not(:disabled)', function() {
                let instanciaId = $(this).closest('tr').data('instancia');
                let tamano = $(this).val();
                
                $.ajax({
                    url: 'buscar_producto.php',
                    method: 'POST',
                    data: { id: $(this).closest('tr').data('producto') },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let producto = response.producto;
                            let precio = 0;
                            
                            if (tamano === '16oz' && producto.precio_16oz) {
                                precio = producto.precio_16oz;
                            } else if (tamano === '20oz' && producto.precio_20oz) {
                                precio = producto.precio_20oz;
                            } else if (producto.precio_fijo) {
                                precio = producto.precio_fijo;
                            }
                            
                            // Actualizar solo la fila específica usando instanciaId
                            $(`tr[data-instancia="${instanciaId}"] .precio`).text('C$ ' + precio.toFixed(0));
                            
                            // Actualizar precio en el array
                            let productoAgregado = productosAgregados.find(p => p.instanciaId == instanciaId);
                            if (productoAgregado) {
                                productoAgregado.precio = precio;
                            }
                            
                            calcularTotales();
                        }
                    }
                });
            });
                        
                       // Modificar el evento para abrir el modal de extras
            $(document).on('click', '.btn-agregar-extra', function() {
                let instanciaId = $(this).closest('tr').data('instancia');
                $('#modal-extras').data('instancia-id', instanciaId).removeClass('hidden');
            });
                        
            // En el modal de extras, cambiar cómo se agregan los extras:
            $(document).on('click', '.btn-agregar-extra-modal', function() {
                let extraId = $(this).closest('.extra-item').data('extra-id');
                let extraNombre = $(this).siblings('span').text().split(' (')[0];
                let extraPrecio = parseFloat($(this).siblings('span').text().match(/\d+\.\d+/)[0]);
                let instanciaId = $('#modal-extras').data('instancia-id');
                
                let extraHtml = `
                    <div class="extra-item" data-extra-id="${extraId}" data-precio="${extraPrecio}">
                        <span>${extraNombre} (C$${extraPrecio})</span>
                        <button type="button" class="btn-eliminar-extra">×</button>
                        <input type="hidden" name="extras[${instanciaId}][]" value="${extraId}">
                    </div>
                `;
                
                $(`tr[data-instancia="${instanciaId}"] .extras-list`).append(extraHtml);
                $('#modal-extras').addClass('hidden');
                calcularTotales();
            });
            
            // Eliminar extra
            $(document).on('click', '.btn-eliminar-extra', function() {
                $(this).closest('.extra-item').remove();
                calcularTotales();
            });
            
            // Eliminar producto
            $(document).on('click', '.btn-eliminar-producto', function() {
                let instanciaId = $(this).closest('tr').data('instancia');
    
    // Eliminar del array usando instanciaId
    productosAgregados = productosAgregados.filter(p => p.instanciaId != instanciaId);
    
    // Eliminar fila
    $(this).closest('tr').remove();
                
                // Si no hay productos, mostrar fila vacía
                if ($('#tabla-productos tbody tr').length === 0) {
                    $('#tabla-productos tbody').append('<tr class="empty-row"><td colspan="9">No hay productos agregados</td></tr>');
                }
                
                calcularTotales();
            });
            
            // Cerrar modales
            $(document).on('click', '.btn-cerrar-modal', function() {
                $(this).closest('.modal').addClass('hidden');
            });
            
            // Calcular totales
            function calcularTotales() {
                let subtotal = 0;
                
                // Sumar productos por instancia
                $('tr[data-instancia]').each(function() {
                    let instanciaId = $(this).data('instancia');
                    let precio = parseFloat($(this).find('.precio').text().replace('C$ ', '')) || 0;
                    let cantidad = parseInt($(this).find('.cantidad').val()) || 1;
                    subtotal += precio * cantidad;
                    
                    // Sumar extras de esta instancia
                    $(this).find('.extra-item').each(function() {
                        let precioExtra = parseFloat($(this).data('precio')) || 0;
                        subtotal += precioExtra * cantidad;
                    });
                });
                
                // Obtener el cargo de delivery (ya sea del select o del input)
                let cargoDelivery = 0;
                if ($('#cargo-delivery-select').length) {
                    cargoDelivery = parseFloat($('#cargo-delivery-select').val()) || 0;
                } else {
                    cargoDelivery = parseFloat($('#cargo-delivery').val()) || 0;
                }
                
                let total = subtotal + cargoDelivery;
                
                // Actualizar los campos en el resumen de pago
                $('#subtotal').text('C$ ' + Math.round(subtotal).toFixed(0));
                $('#delivery-total').text('C$ ' + Math.round(cargoDelivery).toFixed(0));
                $('#total').text('C$ ' + Math.round(total).toFixed(0));
                
                // Calcular dólares
                let totalDolares = (total / tipoCambio).toFixed(1);
                $('#total-dolares').text('$ ' + totalDolares);
                
                // Calcular cambio si es pago en efectivo
                if ($('#metodo-pago').val() === 'efectivo') {
                    calcularCambio();
                }
            }
            
            // Asegurarse de que el cambio en el cargo de delivery actualice los totales
            $(document).on('change', '#cargo-delivery, #cargo-delivery-select', function() {
                calcularTotales();
            });
            
            // Autocompletar cliente por teléfono
            $('#telefono').on('input', function() {
                let telefono = $(this).val().trim().replace(/\D/g, ''); // Solo números
                $(this).val(telefono); // Actualizar el valor sin caracteres no numéricos
                
                // Limpiar campos si el teléfono es muy corto
                if (telefono.length < 7) {
                    $('#nombre').val('').prop('readonly', false);
                    $('#direccion').val('');
                    $('#codigo-club').val('0').prop('readonly', false);
                    $('#resultados-cliente').remove(); // Eliminar resultados anteriores
                    return;
                }
                
                // Mostrar loader durante la búsqueda
                $(this).after('<div id="buscando-cliente" style="color: #666; font-size: 0.8em;">Buscando cliente...</div>');
                
                $.ajax({
                    url: 'buscar_cliente.php',
                    method: 'POST',
                    data: { telefono: telefono },
                    dataType: 'json',
                    success: function(response) {
                        $('#buscando-cliente').remove();
                        
                        if (response.success) {
                            if (response.clientes && response.clientes.length > 1) {
                                // Mostrar múltiples coincidencias
                                mostrarCoincidenciasClientes(response.clientes);
                            } else if (response.cliente) {
                                // Solo un cliente encontrado
                                autocompletarCliente(response.cliente);
                            } else {
                                // No hay coincidencias
                                $('#nombre').val('').prop('readonly', false);
                                $('#codigo-club').val('0').prop('readonly', false);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#buscando-cliente').remove();
                        console.error('Error al buscar cliente:', error);
                    }
                });
            });
            
            function mostrarCoincidenciasClientes(clientes) {
                // Eliminar resultados anteriores
                $('#resultados-cliente').remove();
                
                // Crear contenedor de resultados
                let contenedor = $('<div id="resultados-cliente" style="margin-top: 5px; border: 1px solid #ddd; max-height: 150px; overflow-y: auto;"></div>');
                
                // Agregar cada cliente como opción
                clientes.forEach(cliente => {
                    let opcion = $(`
                        <div style="padding: 8px; border-bottom: 1px solid #eee; cursor: pointer;">
                            ${cliente.nombre} - ${cliente.telefono}
                            <small style="display: block; color: #666;">${cliente.direccion || 'Sin dirección'}</small>
                        </div>
                    `);
                    
                    opcion.click(function() {
                        autocompletarCliente(cliente);
                        contenedor.remove();
                    });
                    
                    contenedor.append(opcion);
                });
                
                $('#telefono').after(contenedor);
            }
            
            function autocompletarCliente(cliente) {
                $('#nombre').val(cliente.nombre).prop('readonly', false);
                
                // Actualizar título de la pestaña
                document.title = `Pedido - ${cliente.nombre} | Batidos Pitaya`;
                
                // Solo establecer dirección si no está en el formulario o si es nueva
                if (!$('#direccion').val() || $('#direccion').val() === '') {
                    $('#direccion').val(cliente.direccion || '');
                }
                
                // Manejar código del club
                if (cliente.codigo && cliente.codigo !== '0') {
                    $('#codigo-club').val(cliente.codigo).prop('readonly', true);
                } else {
                    $('#codigo-club').val('0').prop('readonly', false);
                }
            }
            
            // Cancelar pedido
            $('#btn-cancelar').click(function() {
                if (confirm('¿Cancelar este pedido sin guardar? Se perderán todos los cambios y se cerrará la pestaña actual...')) {
                    window.close();
                }
            });
            
            // Validar formulario antes de enviar
            $('#form-pedido').submit(function(e) {
                e.preventDefault();
                
                // Validar teléfono si está presente
                const telefono = $('#telefono').val().trim();
                if (telefono && telefono.length !== 8) {
                    alert('El teléfono debe tener exactamente 8 dígitos');
                    return false;
                }
                
                // Verificar si hay productos
                if ($('#tabla-productos tbody tr[data-producto]').length === 0) {
                    alert('Debe agregar al menos un producto');
                    return false;
                }
                
                if (!confirm('¿Guardar y marcar Pedido como completado? Esta acción no podrá deshacerse...')) {
                    return false;
                }
                
                // Mostrar loader
                $('#btn-guardar').addClass('btn-loading').html('<i class="fas fa-spinner fa-spin"></i> Procesando...');
                
                // 1. Generar captura y copiar al portapapeles
                generarCapturaPedido().then(() => {
                    // 2. Guardar el pedido
                    guardarPedido();
                }).catch(error => {
                    console.error('Error al generar captura:', error);
                    $('#btn-guardar').removeClass('btn-loading').html('<i class="fas fa-save"></i> Guardar y Capturar');
                    alert('Error al generar la captura');
                });
            });

            // Función para generar la captura del pedido
            function generarCapturaPedido() {
                return new Promise((resolve, reject) => {
                    // Crear contenedor temporal para la captura
                    const captureContainer = $('<div id="temp-capture-container" style="position:absolute; left:-9999px; width: 800px; background: white; padding: 25px; font-family: Arial, sans-serif;"></div>');
                    
                    // Clonar secciones superiores (cliente y servicio)
                    const seccionesSuperiores = $('.secciones-superiores').clone();
                    
                    // 1. Procesar Indicaciones Especiales
                    const indicaciones = seccionesSuperiores.find('#indicaciones');
                    const indicacionesText = indicaciones.val().trim();
                    if (indicacionesText) {
                        indicaciones.replaceWith(`
                            <div id="indicaciones-capture" style="
                                padding: 8px 12px;
                                background-color: #bfd113;
                                font-weight: bold;
                                margin-bottom: 15px;
                            ">${indicacionesText}</div>
                        `);
                    } else {
                        indicaciones.replaceWith('<div style="padding: 5px;">Ninguna</div>');
                    }
                    
                    // 2. Mostrar Empresa Delivery seleccionada
                    const empresaDelivery = $('#empresa-delivery option:selected').text();
                    if (empresaDelivery) {
                        seccionesSuperiores.find('#empresa-delivery').replaceWith(`
                            <div style="padding: 5px; margin-bottom: 15px;">
                                ${empresaDelivery}
                            </div>
                        `);
                    }
                    
                    // 3. Procesar campos de formulario en las secciones superiores
                    seccionesSuperiores.find('input, select, textarea, button').each(function() {
                        if ($(this).is('select')) {
                            const selectedText = $(this).find('option:selected').text();
                            $(this).replaceWith(`<div style="padding: 5px;">${selectedText}</div>`);
                        } else if ($(this).is('input, textarea') && $(this).attr('id') !== 'indicaciones-capture') {
                            $(this).replaceWith(`<div style="padding: 5px;">${$(this).val()}</div>`);
                        } else if (!$(this).is('textarea')) {
                            $(this).remove();
                        }
                    });
                    
                    // 4. Clonar sección de pago y procesar según método seleccionado
                    const seccionPago = $('.seccion:eq(3)').clone();
                    seccionPago.find('button').remove();
                    
                    // Mostrar campos según método de pago
                    const metodoPago = $('#metodo-pago option:selected').text();
                    let detallesPago = `<div><strong>Método:</strong> ${metodoPago}</div>`;
                    
                    if ($('#metodo-pago').val() === 'efectivo') {
                        detallesPago += `
                            <div><strong>Pago Recibido (C$):</strong> ${$('#pago-cordobas').val() || '0'}</div>
                            <div><strong>Pago Recibido ($):</strong> ${$('#pago-dolares').val() || '0'}</div>
                            <div><strong>Cambio (C$):</strong> ${$('#cambio').val() || '0'}</div>
                        `;
                    } else if ($('#metodo-pago').val() === 'transferencia') {
                        detallesPago += `<div style="margin-top: 10px;">
                            <img src="../../assets/img/bancos_pagopitaya.png" style="max-width: 100%; height: auto; border: 1px solid #ddd;">
                            <p style="margin-top: 5px; font-size: 0.9em;">
                                <strong>Nota:</strong> Por favor enviar comprobante al WhatsApp de Batidos Pitaya.
                            </p>
                        </div>`;
                    }
                    
                    seccionPago.find('.metodo-pago').html(detallesPago);
                    
                    // 5. Clonar tabla de productos con los ajustes necesarios
                    const tablaProductos = $('#tabla-productos').clone();
                    
                    // Eliminar la columna de Acciones (última columna)
                    tablaProductos.find('th:last-child, td:last-child').remove();
                    
                    // Eliminar botones y columnas no necesarias
                    tablaProductos.find('.btn-cantidad, .btn-agregar-extra, .btn-eliminar-producto').remove();
                    
                    // Procesar cada fila de productos
                    tablaProductos.find('tr[data-producto]').each(function() {
                        const $row = $(this);
                        const producto = $row.find('td:eq(0)').text().trim();
                        const tamano = $row.find('.tamano option:selected').text();
                        const cantidad = $row.find('.cantidad').val();
                        const precio = $row.find('.precio').text();
                        const endulzante = $row.find('.endulzante option:selected').text();
                        const promocion = $row.find('.promocion option:selected').text();
                        const notas = $row.find('.notas-producto').val().trim();
                        
                        // Procesar extras
                        const extras = [];
                        $row.find('.extra-item').each(function() {
                            const extraText = $(this).find('span').text().trim();
                            extras.push(extraText);
                        });
                        
                        // Reconstruir la fila
                        let newRow = `
                            <tr>
                                <td>${producto}</td>
                                <td>${tamano}</td>
                                <td>${cantidad}</td>
                                <td>${precio}</td>
                                <td>${endulzante}</td>
                                <td>${extras.length > 0 ? extras.join('<br>') : 'Ninguno'}</td>
                                <td>${promocion || 'Ninguna'}</td>
                                <td>${notas || 'Ninguna'}</td>
                            </tr>
                        `;
                        
                        $row.replaceWith(newRow);
                    });
                    
                    // Construir el contenido de la captura con estilos mejorados
                    const contenido = `
                        <style>
                            #temp-capture-container {
                                font-family: Arial, sans-serif;
                                color: #333;
                            }
                            #temp-capture-container h2, 
                            #temp-capture-container h3 {
                                color: #0E544C;
                            }
                            #temp-capture-container table {
                                width: 100%;
                                border-collapse: collapse;
                                margin: 15px 0;
                                font-size: 14px;
                            }
                            #temp-capture-container th {
                                background-color: #0E544C;
                                color: white;
                                padding: 8px;
                                text-align: left;
                            }
                            #temp-capture-container td {
                                padding: 8px;
                                border-bottom: 1px solid #ddd;
                            }
                            #temp-capture-container .total-item {
                                display: flex;
                                justify-content: space-between;
                                margin-bottom: 8px;
                            }
                            #temp-capture-container .total-final {
                                font-weight: bold;
                                font-size: 1.1em;
                                margin-top: 10px;
                            }
                            #temp-capture-container hr {
                                border: none;
                                border-top: 1px dashed #ccc;
                                margin: 15px 0;
                            }
                        </style>
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h2 style="margin-bottom: 5px;">Batidos Pitaya</h2>
                            <p style="font-size: 0.9em; color: #666;">
                                Pedido #${$('#codigo-pedido').text()} - ${$('#fecha-hora').text()}
                            </p>
                        </div>
                        
                        ${seccionesSuperiores.prop('outerHTML')}
                        
                        <h3 style="margin-bottom: 10px;">Productos</h3>
                        ${tablaProductos.prop('outerHTML')}
                        
                        <h3 style="margin-bottom: 10px;">Resumen de Pago</h3>
                        ${seccionPago.prop('outerHTML')}
                        
                        <div style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #666;">
                            ¡Gracias por su compra!
                        </div>
                    `;
                    
                    captureContainer.html(contenido);
                    $('body').append(captureContainer);
                    
                    // Generar la imagen
                    html2canvas(captureContainer[0], {
                        scale: 2,
                        logging: false,
                        useCORS: true,
                        backgroundColor: '#fff',
                        letterRendering: true
                    }).then(canvas => {
                        // Copiar al portapapeles
                        canvas.toBlob(blob => {
                            navigator.clipboard.write([
                                new ClipboardItem({ 'image/png': blob })
                            ]).then(() => {
                                console.log('Captura copiada al portapapeles');
                                $('#temp-capture-container').remove();
                                resolve();
                            }).catch(err => {
                                console.error('Error al copiar:', err);
                                $('#temp-capture-container').remove();
                                resolve(); // Continuamos aunque falle el copiado
                            });
                        });
                    }).catch(err => {
                        $('#temp-capture-container').remove();
                        reject(err);
                    });
                });
            }
            
            // Función para guardar el pedido
            function guardarPedido() {
                let formData = new FormData($('#form-pedido')[0]);
                formData.append('estado', 'enviado_cliente');
                
                $.ajax({
                    url: 'procesar_pedido.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            mostrarNotificacion('Pedido guardado y captura copiada', 'success');
                            setTimeout(() => window.close(), 1500);
                        } else {
                            $('#btn-guardar').removeClass('btn-loading').html('<i class="fas fa-save"></i> Guardar y Capturar');
                            alert(response.error || 'Error al guardar el pedido');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#btn-guardar').removeClass('btn-loading').html('<i class="fas fa-save"></i> Guardar y Capturar');
                        alert('Error en la conexión: ' + error);
                    }
                });
            }

            //Para que se identifique las sucursales de Managua y ocultar los campos de Distancia y Conductor
            function esSucursalManagua(sucursalId) {
                const sucursalesManagua = [4, 5, 7, 9]; // IDs de sucursales de Managua
                return sucursalesManagua.includes(parseInt(sucursalId));
            }
            
            $('#sucursal').change(function() {
                let sucursalId = $(this).val();
                
                // Resetear campos de delivery
                $('#empresa-delivery').val('').trigger('change');
                $('#distancia').val('0');
                $('#conductor').val('');
                
                // Mostrar/ocultar campos según si es sucursal de Managua
                if (esSucursalManagua(sucursalId)) {
                    $('#distancia').closest('.form-group').show();
                    $('#conductor').closest('.form-group').show();
                } else {
                    $('#distancia').closest('.form-group').hide();
                    $('#conductor').closest('.form-group').hide();
                    $('#distancia').val('0');
                    $('#conductor').val('');
                }
                
                $.ajax({
                    url: 'buscar_empresas_delivery.php',
                    method: 'POST',
                    data: { sucursal_id: sucursalId },
                    dataType: 'json',
                    success: function(response) {
                        let select = $('#empresa-delivery');
                        select.empty().append('<option value="">Seleccione una empresa</option>');
                        
                        if (response.success && response.empresas.length > 0) {
                            response.empresas.forEach(empresa => {
                                select.append(`<option value="${empresa.id}">${empresa.nombre}</option>`);
                            });
                            select.removeClass('hidden');
                        } else {
                            select.addClass('hidden');
                        }
                    }
                });
            });
            
            // Llamar al cambio al cargar la página si es edición
            if ($('#tipo-servicio').val() === 'delivery') {
                $('#sucursal').trigger('change');
            }
            
            // Manejar impresión
            $('#btn-imprimir').click(function() {
                if ($('#tabla-productos tbody tr[data-producto]').length === 0) {
                    alert('No hay productos en el pedido');
                    return;
                }
            
                // Generar el texto del resumen
                let resumen = `*Pedido Pitaya - ${$('#codigo-pedido').text()}*\n`;
                resumen += `*Fecha:* ${$('#fecha-hora').text()}\n`;
                resumen += `*Cliente:* ${$('#nombre').val() || 'Sin nombre'}\n`;
                resumen += `*Teléfono:* ${$('#telefono').val() || 'Sin teléfono'}\n\n`;
                resumen += `*Productos:*\n`;
            
                // Agregar cada producto
                $('#tabla-productos tbody tr[data-producto]').each(function() {
                    let nombre = $(this).find('td:eq(0)').text().trim();
                    let tamano = $(this).find('.tamano option:selected').text();
                    let cantidad = $(this).find('.cantidad').val();
                    let precio = $(this).find('.precio').text();
                    
                    resumen += `- ${cantidad}x ${nombre} (${tamano}) ${precio}\n`;
                    
                    // Agregar extras si existen
                    $(this).find('.extra-item').each(function() {
                        let extra = $(this).find('span').text().trim();
                        resumen += `  + ${extra}\n`;
                    });
                });
            
                resumen += `\n*Subtotal:* ${$('#subtotal').text()}\n`;
                resumen += `*Delivery:* ${$('#delivery-total').text()}\n`;
                resumen += `*Total:* ${$('#total').text()} (${$('#total-dolares').text()})\n`;
                resumen += `*Notas:* ${$('#indicaciones').val() || 'Ninguna'}`;
            
                // Copiar al portapapeles
                navigator.clipboard.writeText(resumen).then(function() {
                    alert('Resumen del pedido copiado al portapapeles!');
                    
                    // Opcional: Generar imagen de la factura
                    generarImagenFactura();
                }).catch(function(err) {
                    console.error('Error al copiar: ', err);
                    // Fallback para navegadores que no soportan clipboard API
                    let textarea = document.createElement('textarea');
                    textarea.value = resumen;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    alert('Resumen copiado!');
                });
            });
            
            // Función para generar imagen de la factura
            function generarImagenFactura() {
                // Crear un div oculto con el formato de factura
                let facturaDiv = document.createElement('div');
                facturaDiv.id = 'factura-impresion';
                facturaDiv.style.position = 'fixed';
                facturaDiv.style.left = '-9999px';
                facturaDiv.style.width = '300px';
                facturaDiv.style.padding = '15px';
                facturaDiv.style.backgroundColor = 'white';
                facturaDiv.style.border = '1px solid #000';
                facturaDiv.style.fontFamily = 'Arial, sans-serif';
                
                // Obtener indicaciones solo si no está vacío
                let indicaciones = $('#indicaciones').val().trim();
                let notasHTML = indicaciones ? `<p><strong>Notas:</strong> ${indicaciones}</p>` : '';
                
                // Contenido de la factura
                facturaDiv.innerHTML = `
                    <h2 style="text-align: center; color: #0E544C; margin-bottom: 10px;">Batidos Pitaya</h2>
                    <p style="text-align: center; margin-bottom: 15px; font-size: 0.9em;">
                        ${$('#codigo-pedido').text()} - ${$('#fecha-hora').text()}
                    </p>
                    <hr style="border-top: 1px dashed #ccc; margin: 10px 0;">
                    <p><strong>Cliente:</strong> ${$('#nombre').val() || 'Sin nombre'}</p>
                    <p><strong>Teléfono:</strong> ${$('#telefono').val() || 'Sin teléfono'}</p>
                    <p><strong>Dirección:</strong> ${$('#direccion').val() || 'No especificada'}</p>
                    <hr style="border-top: 1px dashed #ccc; margin: 10px 0;">
                    <h3 style="margin-bottom: 5px;">Productos:</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                        ${generarTablaFactura()}
                    </table>
                    <hr style="border-top: 1px dashed #ccc; margin: 10px 0;">
                    <p style="text-align: right;"><strong>Subtotal:</strong> ${$('#subtotal').text()}</p>
                    <p style="text-align: right;"><strong>Delivery:</strong> ${$('#delivery-total').text()}</p>
                    <p style="text-align: right; font-size: 1.1em;"><strong>Total:</strong> ${$('#total').text()}</p>
                    <p style="text-align: right; font-size: 0.9em;">${$('#total-dolares').text()}</p>
                    ${notasHTML}
                    <p style="text-align: center; margin-top: 20px; font-size: 0.8em;">¡Gracias por su compra!</p>
                `;
                
                document.body.appendChild(facturaDiv);
                
                // Usar html2canvas con configuración para mejor calidad
                if (typeof html2canvas !== 'undefined') {
                    html2canvas(facturaDiv, {
                        scale: 2, // Aumentar calidad
                        logging: false,
                        useCORS: true,
                        backgroundColor: '#fff',
                        allowTaint: true
                    }).then(canvas => {
                        // Mostrar la imagen en una nueva ventana
                        let ventana = window.open('', '_blank');
                        ventana.document.write('<img src="' + canvas.toDataURL('image/png', 1.0) + '" />');
                        ventana.document.close();
                        
                        // Opción para descargar la imagen
                        let link = document.createElement('a');
                        link.download = 'factura-pitaya-' + $('#codigo-pedido').text() + '.png';
                        link.href = canvas.toDataURL('image/png', 1.0);
                        link.click();
                    });
                }
                
                // Eliminar el div temporal
                document.body.removeChild(facturaDiv);
            }
            
            // Modificar la función generarTablaFactura() para excluir botones
            function generarTablaFactura() {
                let html = `
                    <style>
                        #temp-capture-container * {
                            font-size: 14px !important;
                            line-height: 1.4 !important;
                        }
                        #temp-capture-container h2, 
                        #temp-capture-container h3 {
                            font-size: 18px !important;
                        }
                        #temp-capture-container table {
                            font-size: 13px !important;
                            width: 100% !important;
                            border-collapse: collapse;
                        }
                        #temp-capture-container th, 
                        #temp-capture-container td {
                            padding: 8px 5px !important;
                            text-align: center !important;
                            vertical-align: middle !important;
                            border-bottom: 1px solid #eee !important;
                        }
                        #temp-capture-container td:first-child {
                            text-align: left !important;
                        }
                        #temp-capture-container td:nth-child(4) {
                            text-align: right !important;
                        }
                        #temp-capture-container td:nth-child(5) {
                            text-align: left !important;
                        }
                        #temp-capture-container .seccion {
                            margin-bottom: 15px !important;
                        }
                        #temp-capture-container .seccion-header {
                            padding: 10px 15px !important;
                        }
                        #temp-capture-container #indicaciones-capture {
                            min-height: 80px;
                            padding: 8px 12px;
                            ${$('#indicaciones').val().trim() ? 'background-color: #bfd113; font-weight: bold;' : ''}
                        }
                    </style>
                    <tr>
                        <th style="width: 30%;">Producto</th>
                        <th style="width: 10%;">Tamaño</th>
                        <th style="width: 10%;">Cant.</th>
                        <th style="width: 15%;">P.U.</th>
                        <th style="width: 20%;">Endulzante</th>
                    </tr>
                `;
                
                $('#tabla-productos tbody tr[data-producto]').each(function() {
                    let nombre = $(this).find('td:eq(0)').text().trim();
                    let tamano = $(this).find('.tamano option:selected').text();
                    let cantidad = $(this).find('.cantidad').val();
                    let precio = $(this).find('.precio').text();
                    let endulzante = $(this).find('.endulzante option:selected').text();
                    
                    html += `
                        <tr>
                            <td>${nombre}</td>
                            <td>${tamano}</td>
                            <td>${cantidad}</td>
                            <td>${precio}</td>
                            <td>${endulzante}</td>
                        </tr>
                    `;
                    
                    // Agregar extras si existen - VERSIÓN CORREGIDA
                    $(this).find('.extra-item').each(function() {
                        // Extraer solo el nombre del extra (eliminando el precio y la ×)
                        let extraText = $(this).find('span').text().trim();
                        extraText = extraText.split(' (')[0]; // Elimina la parte del precio
                        
                        html += `
                            <tr>
                                <td colspan="5" style="text-align: left !important; padding-left: 20px !important; font-size: 12px !important;">
                                    + ${extraText}
                                </td>
                            </tr>
                        `;
                    });
                });
                
                return html;
            }
            
            // Función para copiar captura de pantalla al portapapeles
            $('#btn-copiar-pantalla').click(async function() {
                const btn = $(this);
                const originalHtml = btn.html();
                
                try {
                    btn.prop('disabled', true).html('<i class="fas fa-camera"></i> Capturando...');
                    
                    // Crear contenedor temporal
                    const captureContainer = $('<div id="temp-capture-container" style="position:absolute; left:-9999px; width: 1000px; background: white; padding: 25px;"></div>');
                    
                    // Clonar secciones superiores
                    const seccionesSuperiores = $('.secciones-superiores').clone();
                    
                    // 1. Mantener estilo de Indicaciones Especiales
                    const indicaciones = seccionesSuperiores.find('#indicaciones');
                    if (indicaciones.val().trim()) {
                        indicaciones.css({
                            'background-color': '#bfd113',
                            'font-weight': 'bold'
                        });
                    }
                    indicaciones.attr('id', 'indicaciones-capture');
                    
                    // 2. Mostrar Empresa Delivery seleccionada
                    const empresaDelivery = $('#empresa-delivery option:selected').text();
                    if (empresaDelivery) {
                        seccionesSuperiores.find('#empresa-delivery').replaceWith(`
                            <div style="padding: 5px; margin-bottom: 15px;">
                                ${empresaDelivery}
                            </div>
                        `);
                    } else {
                        seccionesSuperiores.find('#empresa-delivery').remove();
                    }
                    
                    // Reemplazar otros controles de formulario
                    seccionesSuperiores.find('input, select, textarea, button').each(function() {
                        if ($(this).is('select')) {
                            $(this).replaceWith(`<div style="padding: 5px;">${$(this).find('option:selected').text()}</div>`);
                        } else if ($(this).is('input, textarea') && $(this).attr('id') !== 'indicaciones-capture') {
                            $(this).replaceWith(`<div style="padding: 5px;">${$(this).val()}</div>`);
                        } else if (!$(this).is('textarea')) {
                            $(this).remove();
                        }
                    });
                    
                    // Clonar sección de productos
                    const seccionProductos = $('.seccion:eq(2)').clone();
                    seccionProductos.find('.categorias-productos, .btn-cantidad, .btn-agregar-extra, .btn-eliminar-producto').remove();
                    seccionProductos.find('th:last-child, td:last-child').remove();
                    
                    // Procesar extras para eliminar botones y números
                    seccionProductos.find('.extra-item').each(function() {
                        let extraText = $(this).find('span').text().trim();
                        extraText = extraText.split(' (')[0]; // Quitar el precio
                        
                        // Reemplazar todo el contenido del extra solo con el texto limpio
                        $(this).html(`<span>${extraText}</span>`);
                        $(this).find('button, input').remove(); // Eliminar botones e inputs ocultos
                    });
                    
                    // 3. Ajuste de texto en tabla de pedidos
                    seccionProductos.find('select, input').each(function() {
                        if ($(this).is('select')) {
                            $(this).replaceWith(`<div style="text-align: center; padding: 3px;">${$(this).find('option:selected').text()}</div>`);
                        } else {
                            $(this).replaceWith(`<div style="text-align: center; padding: 3px;">${$(this).val()}</div>`);
                        }
                    });
                    
                    // Asegurar alineación en la tabla
                    seccionProductos.find('table').css('font-size', '13px');
                    seccionProductos.find('th, td').css({
                        'padding': '8px 5px',
                        'text-align': 'center',
                        'vertical-align': 'middle'
                    });
                    seccionProductos.find('td:first-child').css('text-align', 'left');
                    seccionProductos.find('td:nth-child(4)').css('text-align', 'right');
                    seccionProductos.find('td:nth-child(5)').css('text-align', 'left');
                    
                    // Clonar sección de pago
                    const seccionPago = $('.seccion:eq(3)').clone();
                    
                    // Aplicar estilos consistentes
                    [seccionesSuperiores, seccionProductos, seccionPago].forEach(section => {
                        section.css({
                            'margin': '15px 0',
                            'box-shadow': 'none',
                            'border': '1px solid #ddd',
                            'width': '100%'
                        });
                        
                        section.find('.seccion-header h2').css('font-size', '16px');
                    });
                    
                    // Agregar las secciones al contenedor
                    captureContainer.append(seccionesSuperiores, seccionProductos, seccionPago);
                    $('body').append(captureContainer);
                    
                    // Captura con alta calidad
                    const canvas = await html2canvas(captureContainer[0], {
                        scale: 3,
                        logging: false,
                        useCORS: true,
                        backgroundColor: '#fff',
                        letterRendering: true
                    });
                    
                    // Copiar al portapapeles
                    canvas.toBlob(async function(blob) {
                        try {
                            await navigator.clipboard.write([
                                new ClipboardItem({ 'image/png': blob })
                            ]);
                            mostrarNotificacion('¡Captura copiada al portapapeles!', 'success');
                        } catch (err) {
                            console.error('Error al copiar:', err);
                            // Fallback: descargar la imagen
                            const link = document.createElement('a');
                            link.download = 'pedido-' + $('#codigo-pedido').text() + '.png';
                            link.href = canvas.toDataURL('image/png', 1.0);
                            link.click();
                        } finally {
                            btn.html(originalHtml).prop('disabled', false);
                            $('#temp-capture-container').remove();
                        }
                    });
                    
                } catch (err) {
                    console.error('Error:', err);
                    mostrarNotificacion('Error en la captura', 'error');
                    btn.html(originalHtml).prop('disabled', false);
                    $('#temp-capture-container').remove();
                }
            });
            
            // Función para mostrar notificaciones bonitas
            function mostrarNotificacion(mensaje, tipo = 'info') {
                const tipos = {
                    success: { icon: 'check-circle', color: '#28a745' },
                    error: { icon: 'exclamation-circle', color: '#dc3545' },
                    info: { icon: 'info-circle', color: '#17a2b8' }
                };
                
                const notif = $(`
                    <div class="notificacion" style="
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        padding: 15px 20px;
                        background: ${tipos[tipo].color};
                        color: white;
                        border-radius: 4px;
                        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        z-index: 10000;
                        animation: fadeIn 0.3s;
                    ">
                        <i class="fas fa-${tipos[tipo].icon}"></i>
                        <span>${mensaje}</span>
                    </div>
                `);
                
                $('body').append(notif);
                
                setTimeout(() => {
                    notif.fadeOut(300, () => notif.remove());
                }, 3000);
            }
            
            // Agregar evento para cambiar el estilo cuando hay texto
            $('#indicaciones').on('input', function() {
                if ($(this).val().trim() !== '') {
                    $(this).css({
                        'background-color': '#bfd113',
                        'font-weight': 'bold'
                    });
                } else {
                    $(this).css({
                        'background-color': '#fff',
                        'font-weight': 'normal'
                    });
                }
            });
            
            $(document).on('input', '#nombre', function() {
                let nombreCliente = $(this).val().trim();
                if (nombreCliente) {
                    document.title = `Pedido - ${nombreCliente} | Batidos Pitaya`;
                } else {
                    document.title = $('h1').text() + ' | Batidos Pitaya';
                }
            });
        });
</script>
</body>
</html>