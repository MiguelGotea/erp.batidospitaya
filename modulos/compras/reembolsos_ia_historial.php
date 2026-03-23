<?php
/**
 * Historial y Nueva Solicitud de Reembolsos con IA
 * Ubicación: /modulos/compras/reembolsos_ia_historial.php
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/database/conexion.php';
require_once '../../core/helpers/funciones.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso (Permiso estandarizado)
if (!tienePermiso('reembolsos_ia_plantilla', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtener proveedores para el select
$stmtProv = $conn->query("SELECT id, nombre FROM proveedores WHERE vigente = 1 ORDER BY nombre ASC");
$proveedores = $stmtProv->fetchAll(PDO::FETCH_ASSOC);

// Obtener historial
$stmtHist = $conn->prepare("
    SELECT s.*, p.nombre as proveedor_nombre, cp.banco, cp.numero_cuenta, o.Nombre as usuario_nombre,
           CONCAT(cc.Codigo, ' - ', cc.Nombre) as ceco_nombre
    FROM reembolsos_solicitudes s
    LEFT JOIN proveedores p ON s.id_proveedor = p.id
    LEFT JOIN cuenta_proveedor cp ON s.id_cuenta_proveedor = cp.id
    LEFT JOIN Operarios o ON s.usuario_registro = o.CodOperario
    LEFT JOIN CentroCostos cc ON s.ceco = cc.Codigo
    ORDER BY s.created_at DESC
");
$stmtHist->execute();
$historial = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resúmenes de Reembolso IA | Pitaya ERP</title>
    
    <!-- Librerías estándar -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos Globales y Específicos -->
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css">
    <link rel="stylesheet" href="css/reembolsos_ia_historial.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>

    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Plantilla: Reembolsos con IA'); ?>
            
            <div class="container-fluid p-3">
                <div class="row mb-4 align-items-center">
                    <div class="col">
                    </div>
                    <div class="col-auto">
                        <?php if (tienePermiso('reembolsos_ia_plantilla', 'nuevo_registro', $cargoOperario)): ?>
                        <a href="reembolsos_ia_nuevo.php" class="btn-floating-pitaya" title="Nuevo Resumen">
                            <i class="fas fa-plus"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Historial -->
                <div class="card premium-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th>Concepto</th>
                                        <th>CECO</th>
                                        <th>Monto</th>
                                        <th>Estado</th>
                                        <th>Registrado por</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($historial)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No hay solicitudes registradas.</td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach ($historial as $reg): ?>
                                    <tr>
                                        <td><?= formatoFechaCorta($reg['fecha_solicitud']) ?></td>
                                        <td><?= $reg['proveedor_nombre'] ?? '<span class="text-muted">N/A</span>' ?></td>
                                        <td><?= $reg['concepto'] ?></td>
                                        <td><span class="badge bg-light text-dark"><?= $reg['ceco_nombre'] ?? $reg['ceco'] ?></span></td>
                                        <td class="fw-bold text-primary"><?= $reg['moneda'] == 'Dolares' ? 'US$' : 'C$' ?> <?= number_format($reg['total_cordobas'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $reg['estado'] == 'pendiente' ? 'warning text-dark' : 'success' ?>">
                                                <?= strtoupper($reg['estado']) ?>
                                            </span>
                                        </td>
                                        <td><?= $reg['usuario_nombre'] ?></td>
                                        <td class="text-center">
                                            <a href="reembolsos_ia_imprimir.php?id=<?= $reg['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Imprimir Reembolso">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalle(<?= $reg['id'] ?>)" title="Ver Detalle">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía: Reembolsos con IA
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-robot me-2"></i> Transcripción con IA
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Sube una foto clara de la factura para que la IA extraiga los conceptos y montos automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-university me-2"></i> Cuentas Bancarias
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El sistema auto-completa los datos bancarios del proveedor seleccionado para facilitar el pago.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function verDetalle(id) {
            location.href = 'reembolsos_ia_nuevo.php?id=' + id;
        }
    </script>

</body>
</html>
