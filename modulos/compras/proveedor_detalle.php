<?php
// proveedor_detalle.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
$cargosPermitidos = [9, 16, 49];
if (!in_array($cargoOperario, $cargosPermitidos)) {
    header('Location: /index.php');
    exit();
}

$permisos = [
    'crear' => in_array($cargoOperario, [9, 16, 49]),
    'editar' => in_array($cargoOperario, [9, 16, 49]),
    'eliminar' => in_array($cargoOperario, [16, 49])
];

// Determinar si es edición o creación
$idProveedor = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$esEdicion = $idProveedor > 0;

$proveedor = null;
if ($esEdicion) {
    $stmt = $conn->prepare("SELECT * FROM proveedores WHERE id = ?");
    $stmt->execute([$idProveedor]);
    $proveedor = $stmt->fetch();
    
    if (!$proveedor) {
        header('Location: proveedores.php');
        exit();
    }
}

// Obtener sucursales para el selector
$stmtSucursales = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE activa = 1 ORDER BY nombre");
$stmtSucursales->execute();
$sucursales = $stmtSucursales->fetchAll();

// Obtener tipos de pago activos
$stmtTiposPago = $conn->prepare("SELECT id, modalidad, tipopago FROM tipo_pago_proveedores WHERE activo = 1 ORDER BY modalidad, tipopago");
$stmtTiposPago->execute();
$tiposPago = $stmtTiposPago->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $esEdicion ? 'Editar Proveedor' : 'Nuevo Proveedor'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/proveedor_detalle.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, $esEdicion ? 'Editar Proveedor' : 'Nuevo Proveedor'); ?>
            
            <div class="container-fluid p-3">
                <div class="row mb-3" style="display:none;">
                    <div class="col-12">
                        <button class="btn btn-secondary" onclick="window.location.href='proveedores.php'">
                            <i class="bi bi-arrow-left"></i> Volver al Listado
                        </button>
                    </div>
                </div>

                <!-- Pestañas -->
                <ul class="nav nav-tabs" id="proveedorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" data-bs-target="#datos" type="button" role="tab">
                            <i class="bi bi-building"></i> Datos Básicos
                        </button>
                    </li>
                    <?php if ($esEdicion): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contactos-tab" data-bs-toggle="tab" data-bs-target="#contactos" type="button" role="tab">
                            <i class="bi bi-person-lines-fill"></i> Contactos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cuentas-tab" data-bs-toggle="tab" data-bs-target="#cuentas" type="button" role="tab">
                            <i class="bi bi-credit-card"></i> Cuentas Bancarias
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab">
                            <i class="bi bi-clock-history"></i> Historial
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="tab-content" id="proveedorTabContent">
                    <!-- Pestaña Datos Básicos -->
                    <div class="tab-pane fade show active" id="datos" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <form id="formDatosBasicos">
                                    <input type="hidden" id="proveedorId" name="id" value="<?php echo $idProveedor; ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nombre" class="form-label">Nombre del Proveedor *</label>
                                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                                   value="<?php echo htmlspecialchars($proveedor['nombre'] ?? ''); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="ruc_nit" class="form-label">RUC/NIT</label>
                                            <input type="text" class="form-control" id="ruc_nit" name="ruc_nit" 
                                                   value="<?php echo htmlspecialchars($proveedor['ruc_nit'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="direccion" class="form-label">Dirección</label>
                                            <textarea class="form-control" id="direccion" name="direccion" rows="2"><?php echo htmlspecialchars($proveedor['direccion'] ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="comprasucursal" class="form-label">Sucursal de Compra</label>
                                            <select class="form-select" id="comprasucursal" name="comprasucursal">
                                                <option value="">-- Sin asignar --</option>
                                                <?php foreach ($sucursales as $sucursal): ?>
                                                <option value="<?php echo $sucursal['codigo']; ?>" 
                                                        <?php echo ($proveedor['comprasucursal'] ?? '') == $sucursal['codigo'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="vigente" class="form-label">Estado *</label>
                                            <select class="form-select" id="vigente" name="vigente" required>
                                                <option value="1" <?php echo ($proveedor['vigente'] ?? 1) == 1 ? 'selected' : ''; ?>>Vigente</option>
                                                <option value="0" <?php echo ($proveedor['vigente'] ?? 1) == 0 ? 'selected' : ''; ?>>No Vigente</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Tipos de Pago -->
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Tipos de Pago Aceptados</label>
                                            <div class="tipos-pago-grid">
                                                <?php foreach ($tiposPago as $tipo): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input tipo-pago-checkbox" type="checkbox" 
                                                           value="<?php echo $tipo['id']; ?>" 
                                                           id="tipo_<?php echo $tipo['id']; ?>"
                                                           <?php 
                                                           if ($esEdicion) {
                                                               $stmtCheck = $conn->prepare("SELECT id FROM proveedor_tipo_pago WHERE id_proveedor = ? AND id_tipo_pago = ?");
                                                               $stmtCheck->execute([$idProveedor, $tipo['id']]);
                                                               echo $stmtCheck->fetch() ? 'checked' : '';
                                                           }
                                                           ?>>
                                                    <label class="form-check-label" for="tipo_<?php echo $tipo['id']; ?>">
                                                        <?php echo htmlspecialchars($tipo['modalidad'] . ' - ' . $tipo['tipopago']); ?>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="notas_internas" class="form-label">Notas Internas</label>
                                            <textarea class="form-control" id="notas_internas" name="notas_internas" rows="3" 
                                                      placeholder="Notas o comentarios internos sobre el proveedor"><?php echo htmlspecialchars($proveedor['notas_internas'] ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <?php if ($permisos['crear'] || ($esEdicion && $permisos['editar'])): ?>
                                    <div class="row">
                                        <div class="col-12">
                                            <button type="button" class="btn btn-primary" onclick="guardarDatosBasicos()">
                                                <i class="bi bi-save"></i> Guardar
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($esEdicion): ?>
                    <!-- Pestaña Contactos -->
                    <div class="tab-pane fade" id="contactos" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Contactos del Proveedor</h5>
                                    <?php if ($permisos['crear']): ?>
                                    <button class="btn btn-sm btn-success" onclick="abrirModalContacto()">
                                        <i class="bi bi-plus-circle"></i> Agregar Contacto
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div id="listaContactos">
                                    <!-- Cargado vía AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña Cuentas -->
                    <div class="tab-pane fade" id="cuentas" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Cuentas Bancarias</h5>
                                    <?php if ($permisos['crear']): ?>
                                    <button class="btn btn-sm btn-success" onclick="abrirModalCuenta()">
                                        <i class="bi bi-plus-circle"></i> Agregar Cuenta
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div id="listaCuentas">
                                    <!-- Cargado vía AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pestaña Historial -->
                    <div class="tab-pane fade" id="historial" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="mb-3">Historial de Cambios</h5>
                                <div id="listaHistorial">
                                    <!-- Cargado vía AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Agregar/Editar Contacto -->
    <div class="modal fade" id="modalContacto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalContactoTitulo">Agregar Contacto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formContacto">
                        <input type="hidden" id="contactoId" name="id">
                        <input type="hidden" name="id_proveedor" value="<?php echo $idProveedor; ?>">
                        
                        <div class="mb-3">
                            <label for="contactoNombre" class="form-label">Nombre del Contacto *</label>
                            <input type="text" class="form-control" id="contactoNombre" name="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label for="contactoTelefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="contactoTelefono" name="telefono">
                        </div>

                        <div class="mb-3">
                            <label for="contactoCargo" class="form-label">Cargo</label>
                            <input type="text" class="form-control" id="contactoCargo" name="cargo">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="contactoPrincipal" name="principal" value="1">
                                <label class="form-check-label" for="contactoPrincipal">
                                    Contacto Principal
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarContacto()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Agregar/Editar Cuenta -->
    <div class="modal fade" id="modalCuenta" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCuentaTitulo">Agregar Cuenta Bancaria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formCuenta">
                        <input type="hidden" id="cuentaId" name="id">
                        <input type="hidden" name="id_proveedor" value="<?php echo $idProveedor; ?>">
                        
                        <div class="mb-3">
                            <label for="cuentaNumero" class="form-label">Número de Cuenta *</label>
                            <input type="text" class="form-control" id="cuentaNumero" name="numero_cuenta" required>
                        </div>

                        <div class="mb-3">
                            <label for="cuentaTitular" class="form-label">Titular *</label>
                            <input type="text" class="form-control" id="cuentaTitular" name="titular" required>
                        </div>

                        <div class="mb-3">
                            <label for="cuentaBanco" class="form-label">Banco *</label>
                            <input type="text" class="form-control" id="cuentaBanco" name="banco" required>
                        </div>

                        <div class="mb-3">
                            <label for="cuentaMoneda" class="form-label">Moneda *</label>
                            <select class="form-select" id="cuentaMoneda" name="moneda" required>
                                <option value="Córdoba">Córdoba</option>
                                <option value="Dólar">Dólar</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cuentaPrincipal" name="principal" value="1">
                                <label class="form-check-label" for="cuentaPrincipal">
                                    Cuenta Principal
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCuenta()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const PERMISOS = <?php echo json_encode($permisos); ?>;
        const ID_PROVEEDOR = <?php echo $idProveedor; ?>;
        const ES_EDICION = <?php echo $esEdicion ? 'true' : 'false'; ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/proveedor_detalle.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>