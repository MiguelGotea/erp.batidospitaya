<?php
/**
 * detalle_vista_pedido_global.php
 * Página de detalle completo de un pedido: datos globales, líneas de productos y análisis IA.
 * GET ?cod_pedido=X&local=Y
 */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/database/conexion.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('historial_pedidos_globales', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeVerMontos   = tienePermiso('historial_pedidos_globales', 'detalle_montos',                  $cargoOperario);
$puedeAnalizarBot = tienePermiso('historial_pedidos_globales', 'analizar_atencion_cliente_bot',    $cargoOperario);

$cod_pedido = isset($_GET['cod_pedido']) ? intval($_GET['cod_pedido']) : null;
$local      = isset($_GET['local'])      ? trim($_GET['local'])        : null;

if (!$cod_pedido || !$local) {
    die('Parámetros inválidos.');
}

// ── SECCIÓN 1: Datos globales del pedido ───────────────────────────────────
$pedidoGlobal = null;
try {
    $stmt = $conn->prepare("
        SELECT
            MAX(v.Sucursal_Nombre)  AS Sucursal_Nombre,
            v.CodPedido,
            v.local,
            MAX(v.Fecha)            AS Fecha,
            MIN(v.Hora)             AS Hora,
            MAX(v.CodCliente)       AS CodCliente,
            CASE
                WHEN MAX(v.CodCliente) > 0
                THEN CONCAT(MAX(c.nombre), ' ', MAX(c.apellido))
                ELSE 'Sin membresía'
            END                     AS NombreCliente,
            MAX(v.Caja)             AS Caja,
            SUM(v.Precio)           AS TotalMonto,
            SUM(v.Puntos)           AS TotalPuntos,
            MAX(v.Modalidad)        AS Modalidad,
            MAX(v.Anulado)          AS Anulado
        FROM VentasGlobalesAccessCSV v
        LEFT JOIN clientesclub c ON v.CodCliente = c.membresia
        WHERE v.CodPedido = :cp AND v.local = :lc
        GROUP BY v.CodPedido, v.local
    ");
    $stmt->execute([':cp' => $cod_pedido, ':lc' => $local]);
    $pedidoGlobal = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pedidoGlobal = null;
}

// Formateo de fecha y hora para la cabecera
$fechaFmt = '';
$horaFmt  = '';
if ($pedidoGlobal) {
    $fechaFmt = $pedidoGlobal['Fecha'] ? date('d/m/Y', strtotime($pedidoGlobal['Fecha'])) : '—';
    $horaFmt  = $pedidoGlobal['Hora']  ? substr($pedidoGlobal['Hora'], 0, 5)             : '—';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido #<?php echo $cod_pedido; ?> — Pitaya ERP</title>
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        /* ── Variables ─────────────────────────────────── */
        :root {
            --verde-pitaya: #0E544C;
            --verde-claro:  #51B8AC;
            --bg-page:      #f0f4f3;
        }
        body {
            background: var(--bg-page);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        /* ── Wrapper principal ─────────────────────────── */
        .detalle-wrapper {
            max-width: 900px;
            margin: 32px auto;
            padding: 0 16px 60px;
        }

        /* ── Botón volver ──────────────────────────────── */
        .btn-volver {
            background: var(--verde-pitaya);
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-volver:hover { background: #1a7a6e; color: white; }

        /* ── Tarjeta de cabecera del pedido ────────────── */
        .pedido-header {
            background: linear-gradient(135deg, var(--verde-pitaya) 0%, #1a7a6e 100%);
            color: white;
            border-radius: 16px;
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 8px 24px rgba(14,84,76,0.25);
            margin-bottom: 24px;
        }
        .pedido-header .icon-wrap {
            width: 64px; height: 64px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }
        .pedido-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .pedido-header .meta { font-size: 13px; opacity: 0.85; }
        .pedido-header .meta span { margin-right: 16px; }

        /* ── Badges de estado cola IA ──────────────────── */
        .badge-estado {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
            margin-left: auto; flex-shrink: 0;
        }
        .estado-completado { background: rgba(40,167,69,0.2);  color: #28a745; border: 1px solid #28a74566; }
        .estado-pendiente  { background: rgba(255,193,7,0.2);   color: #ffc107; border: 1px solid #ffc10766; }
        .estado-procesando { background: rgba(13,110,253,0.2);  color: #0d6efd; border: 1px solid #0d6efd66; }
        .estado-fallido    { background: rgba(220,53,69,0.2);   color: #dc3545; border: 1px solid #dc354566; }
        .estado-sin-cola   { background: rgba(108,117,125,0.2); color: #6c757d; border: 1px solid #6c757d66; }

        /* ── Tarjeta de sección genérica ───────────────── */
        .seccion-card {
            background: white;
            border-radius: 14px;
            padding: 24px 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .seccion-titulo {
            font-size: 16px;
            font-weight: 700;
            color: var(--verde-pitaya);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid var(--bg-page);
            padding-bottom: 12px;
        }

        /* ── Grid de info del pedido (Sección 1) ────────── */
        .info-grid-pedido {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .info-item-pedido label {
            font-size: 11px;
            color: #adb5bd;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
            display: block;
        }
        .info-item-pedido span {
            font-size: 15px;
            font-weight: 600;
            color: #343a40;
        }

        /* ── Badges Anulado ────────────────────────────── */
        .badge-anulado-si {
            background: #fee2e2; color: #991b1b;
            padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
        }
        .badge-anulado-no {
            background: #d1fae5; color: #065f46;
            padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
        }

        /* ── Scores IA ─────────────────────────────────── */
        .scores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .score-card {
            background: white;
            border-radius: 14px;
            padding: 22px 20px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            transition: transform 0.2s;
            position: relative;
            overflow: hidden;
        }
        .score-card:hover { transform: translateY(-3px); }
        .score-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: 14px 14px 0 0;
        }
        .score-card.amabilidad::before  { background: linear-gradient(90deg,#51B8AC,#0E544C); }
        .score-card.saludo::before      { background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
        .score-card.despedida::before   { background: linear-gradient(90deg,#f59e0b,#d97706); }
        .score-card.membresia::before   { background: linear-gradient(90deg,#8b5cf6,#6d28d9); }
        .score-card.promedio-card::before { background: linear-gradient(90deg,#10b981,#059669); }

        .score-label {
            font-size: 12px; font-weight: 600; color: #6c757d;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;
        }
        .score-icon  { font-size: 24px; margin-bottom: 8px; }
        .score-value { font-size: 42px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
        .score-max   { font-size: 13px; color: #adb5bd; }
        .score-bar   { height: 6px; background: #e9ecef; border-radius: 3px; margin-top: 12px; overflow: hidden; }
        .score-bar-fill { height: 100%; border-radius: 3px; transition: width 1s ease; }
        .score-na    { font-size: 22px; font-weight: 700; color: #adb5bd; margin: 8px 0 4px; }

        .amabilidad  .score-value    { color: #0E544C; }
        .amabilidad  .score-bar-fill { background: linear-gradient(90deg,#51B8AC,#0E544C); }
        .saludo      .score-value    { color: #1d4ed8; }
        .saludo      .score-bar-fill { background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
        .despedida   .score-value    { color: #d97706; }
        .despedida   .score-bar-fill { background: linear-gradient(90deg,#f59e0b,#d97706); }
        .membresia   .score-value    { color: #6d28d9; }
        .membresia   .score-bar-fill { background: linear-gradient(90deg,#8b5cf6,#6d28d9); }
        .promedio-card .score-value  { color: #059669; }
        .promedio-card .score-bar-fill { background: linear-gradient(90deg,#10b981,#059669); }

        /* ── Grupos protocolo Pitaya ────────────────────── */
        .score-card.g-bienvenida::before { background: linear-gradient(90deg,#51B8AC,#0E544C); }
        .score-card.g-asesoria::before   { background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
        .score-card.g-membresia::before  { background: linear-gradient(90deg,#8b5cf6,#6d28d9); }
        .score-card.g-cobro::before      { background: linear-gradient(90deg,#f59e0b,#b45309); }
        .score-card.g-entrega::before    { background: linear-gradient(90deg,#ec4899,#be185d); }
        .g-bienvenida .score-value    { color: #0E544C; }
        .g-bienvenida .score-bar-fill { background: linear-gradient(90deg,#51B8AC,#0E544C); }
        .g-asesoria   .score-value    { color: #1d4ed8; }
        .g-asesoria   .score-bar-fill { background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
        .g-membresia  .score-value    { color: #6d28d9; }
        .g-membresia  .score-bar-fill { background: linear-gradient(90deg,#8b5cf6,#6d28d9); }
        .g-cobro      .score-value    { color: #b45309; }
        .g-cobro      .score-bar-fill { background: linear-gradient(90deg,#f59e0b,#b45309); }
        .g-entrega    .score-value    { color: #be185d; }
        .g-entrega    .score-bar-fill { background: linear-gradient(90deg,#ec4899,#be185d); }
        .score-sublabel { font-size:10px; color:#adb5bd; font-weight:600; letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px; }

        /* ── Veredicto IA ──────────────────────────────── */
        .veredicto-card {
            background: white; border-radius: 14px; padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 24px;
        }
        .veredicto-card h5 {
            color: var(--verde-pitaya); font-weight: 700; margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .veredicto-texto {
            font-size: 15px; line-height: 1.7; color: #343a40;
            background: #f8fffe;
            border-left: 4px solid var(--verde-claro);
            padding: 16px 20px; border-radius: 0 8px 8px 0;
        }

        /* ── Info técnica ──────────────────────────────── */
        .info-tecnica {
            background: white; border-radius: 14px; padding: 20px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 24px;
        }
        .info-tecnica h6 {
            color: #6c757d; font-size: 11px; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 14px; font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .info-item label {
            font-size: 11px; color: #adb5bd; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 2px; display: block;
        }
        .info-item span { font-size: 14px; font-weight: 600; color: #343a40; }

        /* ── Estados especiales IA ─────────────────────── */
        .estado-placeholder {
            background: white; border-radius: 14px; padding: 60px 32px;
            text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        }
        .estado-placeholder .icon-big { font-size: 56px; margin-bottom: 16px; }
        .estado-placeholder h4 { font-weight: 700; margin-bottom: 8px; }
        .estado-placeholder p  { color: #6c757d; max-width: 400px; margin: 0 auto; }

        /* Spinner */
        .spinner-ring {
            display: inline-block; width: 56px; height: 56px;
            border: 5px solid #e9ecef; border-top-color: var(--verde-claro);
            border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Semáforo de calidad */
        .quality-indicator {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
        }
        .quality-alta  { background: #d1fae5; color: #065f46; }
        .quality-media { background: #fef3c7; color: #92400e; }
        .quality-baja  { background: #fee2e2; color: #991b1b; }

        /* ── Tabla líneas ──────────────────────────────── */
        .tabla-lineas thead th {
            background: var(--verde-pitaya);
            color: white;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
        }
        .tabla-lineas tbody tr:hover { background: #f0fffe; }
        .tabla-lineas td { font-size: 13px; vertical-align: middle; }

        /* ── Responsive ────────────────────────────────── */
        @media (max-width: 576px) {
            .pedido-header  { flex-wrap: wrap; }
            .badge-estado   { margin-left: 0; }
            .scores-grid    { grid-template-columns: 1fr 1fr; }
            .info-grid-pedido { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Detalle del Pedido #' . $cod_pedido); ?>

            <div class="container-fluid p-3">
                <div class="detalle-wrapper">

                    <!-- Breadcrumb -->
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <a href="historial_ventas.php" class="btn-volver">
                            <i class="bi bi-arrow-left"></i> Volver al Historial
                        </a>
                        <span class="text-muted" style="font-size:13px;">
                            Pedido #<?php echo $cod_pedido; ?> &middot; Local <?php echo htmlspecialchars($local); ?>
                        </span>
                    </div>

<?php if (!$pedidoGlobal): ?>
                    <!-- Pedido no encontrado -->
                    <div class="estado-placeholder">
                        <div class="icon-big">🔍</div>
                        <h4>Pedido no encontrado</h4>
                        <p>No se encontraron datos para el pedido #<?php echo $cod_pedido; ?> en el local <?php echo htmlspecialchars($local); ?>.</p>
                        <a href="historial_ventas.php" class="btn-volver mt-3">
                            <i class="bi bi-arrow-left"></i> Volver al Historial
                        </a>
                    </div>

<?php else: ?>
                    <!-- ══════════════════════════════════════════════ -->
                    <!--  SECCIÓN 1 — Datos Globales del Pedido        -->
                    <!-- ══════════════════════════════════════════════ -->
                    <div class="seccion-card">
                        <div class="seccion-titulo">
                            🧾 Datos del Pedido
                        </div>
                        <div class="info-grid-pedido">

                            <div class="info-item-pedido">
                                <label><i class="bi bi-building"></i> Sucursal</label>
                                <span><?php echo htmlspecialchars($pedidoGlobal['Sucursal_Nombre'] ?? '—'); ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-hash"></i> Pedido</label>
                                <span>#<?php echo $pedidoGlobal['CodPedido']; ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-calendar3"></i> Fecha</label>
                                <span><?php echo $fechaFmt; ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-clock"></i> Hora</label>
                                <span><?php echo $horaFmt; ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-person-badge"></i> Membresía</label>
                                <span>
                                    <?php echo ($pedidoGlobal['CodCliente'] && intval($pedidoGlobal['CodCliente']) !== 0)
                                        ? htmlspecialchars($pedidoGlobal['CodCliente'])
                                        : '—'; ?>
                                </span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-person"></i> Cliente</label>
                                <span><?php echo htmlspecialchars($pedidoGlobal['NombreCliente'] ?? '—'); ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-pc-display"></i> Cajero / Caja</label>
                                <span><?php echo htmlspecialchars($pedidoGlobal['Caja'] ?? '—'); ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-bag"></i> Modalidad</label>
                                <span><?php echo htmlspecialchars($pedidoGlobal['Modalidad'] ?? '—'); ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-slash-circle"></i> Anulado</label>
                                <span>
                                    <?php if (intval($pedidoGlobal['Anulado']) !== 0): ?>
                                        <span class="badge-anulado-si">SÍ</span>
                                    <?php else: ?>
                                        <span class="badge-anulado-no">NO</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ($puedeVerMontos): ?>
                            <div class="info-item-pedido">
                                <label><i class="bi bi-currency-dollar"></i> Total Monto</label>
                                <span>C$ <?php echo number_format(floatval($pedidoGlobal['TotalMonto'] ?? 0), 2); ?></span>
                            </div>

                            <div class="info-item-pedido">
                                <label><i class="bi bi-star"></i> Total Puntos</label>
                                <span><?php echo intval($pedidoGlobal['TotalPuntos'] ?? 0); ?></span>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                    <!-- /SECCIÓN 1 -->

<?php
// ── SECCIÓN 2: Líneas del pedido ───────────────────────────────────────────
$lineas = [];
try {
    $stmtL = $conn->prepare("
        SELECT v.*
        FROM VentasGlobalesAccessCSV v
        LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
        WHERE v.CodPedido = :cp
          AND v.local     = :lc
          AND (b.CodGrupo IS NULL OR (b.CodGrupo != 25 AND b.CodGrupo != 11))
        ORDER BY v.Hora ASC
    ");
    $stmtL->execute([':cp' => $cod_pedido, ':lc' => $local]);
    $lineas = $stmtL->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lineas = [];
}

// Columnas prioritarias que van al frente de la tabla
$colsPrioritarias = ['DBBatidos_Nombre', 'Medida', 'Cantidad', 'Precio', 'Puntos'];
// Cabeceras amigables para columnas prioritarias
$cabecerasPrior = [
    'DBBatidos_Nombre' => 'Producto',
    'Medida'           => 'Medida',
    'Cantidad'         => 'Cantidad',
    'Precio'           => 'Precio',
    'Puntos'           => 'Puntos',
];

// Determinar todas las columnas de v.* a partir del primer registro
$todasColumnas  = $lineas ? array_keys($lineas[0]) : [];
$colsSecundarias = array_diff($todasColumnas, $colsPrioritarias);

// Orden final de columnas
$colsOrdenadas = [];
foreach ($colsPrioritarias as $c) {
    if (in_array($c, $todasColumnas)) $colsOrdenadas[] = $c;
}
foreach ($colsSecundarias as $c) {
    $colsOrdenadas[] = $c;
}
?>
                    <!-- ══════════════════════════════════════════════ -->
                    <!--  SECCIÓN 2 — Líneas del Pedido                -->
                    <!-- ══════════════════════════════════════════════ -->
                    <div class="seccion-card">
                        <div class="seccion-titulo">
                            📋 Detalle de Líneas
                            <span class="ms-auto badge bg-secondary" style="font-size:11px;">
                                <?php echo count($lineas); ?> producto<?php echo count($lineas) !== 1 ? 's' : ''; ?>
                            </span>
                        </div>

                        <?php if (empty($lineas)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-2"></i>
                            <p class="mt-2">No se encontraron líneas para este pedido.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover tabla-lineas mb-0">
                                <thead>
                                    <tr>
                                        <?php foreach ($colsOrdenadas as $col):
                                            // Ocultar Precio si no tiene permiso
                                            if ($col === 'Precio' && !$puedeVerMontos) continue;
                                            $label = $cabecerasPrior[$col] ?? ucfirst(str_replace('_', ' ', $col));
                                        ?>
                                        <th><?php echo htmlspecialchars($label); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lineas as $linea): ?>
                                    <tr>
                                        <?php foreach ($colsOrdenadas as $col):
                                            if ($col === 'Precio' && !$puedeVerMontos) continue;
                                            $val = $linea[$col] ?? '—';
                                            // Formatear precio con 2 decimales
                                            if ($col === 'Precio' && is_numeric($val)) {
                                                $val = 'C$ ' . number_format((float)$val, 2);
                                            }
                                        ?>
                                        <td><?php echo htmlspecialchars($val); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- /SECCIÓN 2 -->

<?php
// ── SECCIÓN 3: Análisis IA ─────────────────────────────────────────────────
$datosIa = null;
if ($puedeAnalizarBot) {
    try {
        $stmtIa = $conn->prepare("
            SELECT
                c.id           AS id_cola,
                c.cod_pedido,
                c.local_codigo,
                c.fecha,
                c.hora_inicio,
                c.hora_fin,
                c.estado,
                c.tipo,
                c.intentos,
                c.error_mensaje,
                c.created_at   AS encolado_en,
                c.updated_at   AS actualizado_en,
                a.id              AS id_analisis,
                a.grupo_bienvenida,
                a.grupo_asesoria,
                a.grupo_membresia,
                a.grupo_cobro,
                a.grupo_entrega,
                a.cal_promedio,
                a.detalle_json,
                a.resumen,
                a.tiene_audio,
                a.duracion_segundos,
                a.modelo_ia,
                a.version_protocolo,
                a.membresia_contexto,
                a.created_at   AS analizado_en,
                c.membresia_contexto AS membresia_contexto_cola,
                s.nombre       AS sucursal_nombre
            FROM hikvision_cola_analisis c
            LEFT JOIN hikvision_analisis_ia_atencion a ON a.id_cola = c.id
            LEFT JOIN sucursales s ON s.codigo = c.local_codigo
            WHERE c.cod_pedido   = :cp
              AND c.local_codigo = :lc
            ORDER BY c.created_at DESC
            LIMIT 1
        ");
        $stmtIa->execute([':cp' => $cod_pedido, ':lc' => $local]);
        $datosIa = $stmtIa->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $datosIa = null;
    }

    // Procesar variables de la sección IA
    if ($datosIa) {
        $estadoIa    = $datosIa['estado'];
        $promedioIa  = isset($datosIa['cal_promedio']) && $datosIa['cal_promedio'] !== null ? (float)$datosIa['cal_promedio'] : null;
        $detalleJson = !empty($datosIa['detalle_json']) ? json_decode($datosIa['detalle_json'], true) : null;
        // membresia_contexto: prefiere el de la tabla de resultados (más confiable), fallback al de la cola
        $membresiaContexto = $datosIa['membresia_contexto'] ?? $datosIa['membresia_contexto_cola'] ?? 'sin_membresia';
        $qualityClass = 'quality-media';
        $qualityLabel = 'Media';
        if ($promedioIa !== null) {
            if ($promedioIa >= 8)    { $qualityClass = 'quality-alta'; $qualityLabel = 'Alta'; }
            elseif ($promedioIa < 5) { $qualityClass = 'quality-baja'; $qualityLabel = 'Baja'; }
        }
    }
}
?>
<?php if ($puedeAnalizarBot): ?>
                    <!-- ══════════════════════════════════════════════ -->
                    <!--  SECCIÓN 3 — Análisis IA                      -->
                    <!-- ══════════════════════════════════════════════ -->
                    <div class="seccion-card">
                        <div class="seccion-titulo">
                            🤖 Análisis IA de Atención al Cliente
                            <button class="btn btn-sm ms-auto d-flex align-items-center gap-1 fw-semibold" style="background:#e8f5e9;color:#0E544C;border:1px solid #51B8AC;border-radius:8px;padding:5px 12px;font-size:12px;" data-bs-toggle="modal" data-bs-target="#modalProtocolo" title="Ver Protocolo Oficial de Atención">
                                <i class="bi bi-clipboard-check"></i> Protocolo Oficial
                            </button>
                        </div>

<?php if (!$datosIa): ?>
                        <!-- Sin registro en cola -->
                        <div class="estado-placeholder">
                            <div class="icon-big">🔍</div>
                            <h4>Sin análisis registrado</h4>
                            <p>Este pedido no ha sido encolado para análisis IA todavía. Ve al historial de ventas y presiona "Analizar Atención".</p>
                        </div>

<?php elseif ($estadoIa === 'pendiente' || $estadoIa === 'procesando'): ?>
                        <!-- En proceso -->
                        <div class="estado-placeholder" id="waitingSection">
                            <div class="spinner-ring"></div>
                            <h4><?php echo $estadoIa === 'pendiente' ? 'En cola de análisis' : 'Analizando video con IA...'; ?></h4>
                            <p>
                                <?php echo $estadoIa === 'pendiente'
                                    ? 'El pedido está esperando ser procesado por el worker. El análisis comenzará en breve.'
                                    : 'El worker está descargando el video y analizando la atención al cliente con Gemini AI. Esto puede tardar 1-2 minutos.'; ?>
                            </p>
                            <p class="mt-3 text-muted" style="font-size:12px;">
                                <i class="bi bi-arrow-repeat"></i> Esta página se actualiza automáticamente cada 8 segundos...
                            </p>
                        </div>

<?php elseif ($estadoIa === 'fallido'): ?>
                        <!-- Error -->
                        <div class="estado-placeholder">
                            <div class="icon-big">⚠️</div>
                            <h4 style="color:#dc3545;">El análisis falló</h4>
                            <p><?php echo htmlspecialchars($datosIa['error_mensaje'] ?? 'Error desconocido. Revisa los logs del worker.'); ?></p>
                            <p class="mt-2 text-muted" style="font-size:12px;">Intentos: <?php echo $datosIa['intentos']; ?></p>
                        </div>

<?php elseif ($estadoIa === 'completado' && $datosIa['id_analisis']): ?>
                        <!-- ✅ Resultado completo -->

                        <!-- Scores grid: 5 grupos del protocolo -->
                        <div class="scores-grid">
                            <?php
                            $grupos = [
                                ['key'=>'grupo_bienvenida','class'=>'g-bienvenida','label'=>'Bienvenida','icon'=>'🤝','pasos'=>'Paso 1'],
                                ['key'=>'grupo_asesoria',  'class'=>'g-asesoria',  'label'=>'Asesoría',  'icon'=>'💬','pasos'=>'Pasos 2–4'],
                                ['key'=>'grupo_membresia', 'class'=>'g-membresia', 'label'=>'Membresía', 'icon'=>'⭐','pasos'=>'Paso 5'],
                                ['key'=>'grupo_cobro',     'class'=>'g-cobro',     'label'=>'Cobro',     'icon'=>'💳','pasos'=>'Pasos 6–8'],
                                ['key'=>'grupo_entrega',   'class'=>'g-entrega',   'label'=>'Entrega',   'icon'=>'🎁','pasos'=>'Pasos 9–10'],
                            ];
                            foreach ($grupos as $g):
                                $val = isset($datosIa[$g['key']]) && $datosIa[$g['key']] !== null ? (int)$datosIa[$g['key']] : null;
                                $pct = $val !== null ? ($val / 10 * 100) : 0;
                                $isMem = $g['key'] === 'grupo_membresia';
                            ?>
                            <div class="score-card <?php echo $g['class']; ?>">
                                <div class="score-icon"><?php echo $g['icon']; ?></div>
                                <div class="score-label"><?php echo $g['label']; ?></div>
                                <div class="score-sublabel"><?php echo $g['pasos']; ?></div>
                                <?php if ($isMem && $membresiaContexto === 'vendida'): ?>
                                    <div class="score-value">10</div>
                                    <div class="score-max">/ 10</div>
                                    <div class="score-bar"><div class="score-bar-fill" style="width:100%"></div></div>
                                    <div class="mt-2"><span style="font-size:10px;background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-weight:700;">✅ Vendió membresía</span></div>
                                <?php elseif ($isMem && $membresiaContexto === 'ya_tenia'): ?>
                                    <div class="score-na">—</div>
                                    <div class="score-max">No aplica</div>
                                    <div class="mt-2"><span style="font-size:10px;background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:10px;font-weight:700;">ℹ️ Cliente ya tenía</span></div>
                                <?php elseif ($val !== null): ?>
                                    <div class="score-value"><?php echo $val; ?></div>
                                    <div class="score-max">/ 10</div>
                                    <div class="score-bar"><div class="score-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                <?php else: ?>
                                    <div class="score-na">N/A</div>
                                    <div class="score-max">No evaluado</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>

                            <!-- Promedio general -->
                            <div class="score-card promedio-card">
                                <div class="score-icon">📊</div>
                                <div class="score-label">Promedio</div>
                                <div class="score-sublabel">General</div>
                                <?php if ($promedioIa !== null): ?>
                                    <div class="score-value"><?php echo number_format($promedioIa, 1); ?></div>
                                    <div class="score-max">/ 10</div>
                                    <div class="score-bar"><div class="score-bar-fill" style="width:<?php echo ($promedioIa/10*100); ?>%"></div></div>
                                    <div class="mt-2"><span class="quality-indicator <?php echo $qualityClass; ?>">Calidad <?php echo $qualityLabel; ?></span></div>
                                <?php else: ?>
                                    <div class="score-na">N/A</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Veredicto de la IA -->
                        <?php if (!empty($datosIa['resumen'])): ?>
                        <div class="veredicto-card">
                            <h5><i class="bi bi-chat-quote-fill"></i> Veredicto de la IA</h5>
                            <div class="veredicto-texto">
                                <?php echo nl2br(htmlspecialchars($datosIa['resumen'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Info técnica -->
                        <div class="info-tecnica">
                            <h6><i class="bi bi-info-circle"></i> Información del análisis</h6>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Modelo IA</label>
                                    <span><?php echo htmlspecialchars($datosIa['modelo_ia'] ?? '—'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Duración video</label>
                                    <span><?php echo $datosIa['duracion_segundos'] ? $datosIa['duracion_segundos'] . 's' : '—'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Audio detectado</label>
                                    <span><?php echo $datosIa['tiene_audio'] ? '✅ Sí' : '❌ No'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Tipo de análisis</label>
                                    <span><?php echo ucfirst($datosIa['tipo'] ?? '—'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Analizado el</label>
                                    <span><?php echo $datosIa['analizado_en'] ? date('d/m/Y H:i', strtotime($datosIa['analizado_en'])) : '—'; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Versión protocolo</label>
                                    <span><?php echo htmlspecialchars($datosIa['version_protocolo'] ?? '1.0'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>ID Cola</label>
                                    <span>#<?php echo $datosIa['id_cola']; ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($detalleJson) && isset($detalleJson['grupos']) && is_array($detalleJson['grupos'])): ?>
                        <!-- Desglose por paso -->
                        <div class="info-tecnica mt-0">
                            <h6><i class="bi bi-list-check"></i> Desglose por paso del protocolo</h6>
                            <?php
                            $grupoLabels = [
                                'bienvenida' => ['label'=>'Grupo 1 — Bienvenida','icon'=>'🤝','color'=>'#0E544C'],
                                'asesoria'   => ['label'=>'Grupo 2 — Asesoría y Venta *','icon'=>'💬','color'=>'#1d4ed8'],
                                'membresia'  => ['label'=>'Grupo 3 — Membresía Club Pitaya','icon'=>'⭐','color'=>'#6d28d9'],
                                'cobro'      => ['label'=>'Grupo 4 — Proceso de Cobro','icon'=>'💳','color'=>'#b45309'],
                                'entrega'    => ['label'=>'Grupo 5 — Entrega y Despedida','icon'=>'🎁','color'=>'#be185d'],
                            ];
                            $pasoLabels = [
                                'paso_1_saludo_inmediato'  => 'Saludo inmediato',
                                'paso_1_sonrisa_contacto'  => 'Sonrisa y contacto visual',
                                'paso_1_energia_positiva'  => 'Energía positiva y cercanía',
                                'paso_2_escucha_activa'    => 'Escucha activa',
                                'paso_2_recomendo'         => 'Recomendó productos/promo',
                                'paso_3_personalizo'       => 'Personalizó (endulzante/toppings)',
                                'paso_4_acompanante'       => 'Sugirió acompañante',
                                'paso_5_pregunto_membresia'=> 'Preguntó membresía',
                                'paso_5_explico_beneficios'=> 'Explicó beneficios',
                                'paso_6_pidio_nombre'      => 'Solicitó nombre del cliente',
                                'paso_6_indico_monto'      => 'Indicó monto y métodos de pago',
                                'paso_7_repitio_orden'     => 'Repitió la orden',
                                'paso_8_pregunto_propina'  => 'Preguntó por propina',
                                'paso_8b_entrego_factura'  => 'Entregó factura',
                                'paso_9_llamo_por_nombre'  => 'Llamó por nombre al entregar',
                                'paso_9_menciono_producto' => 'Mencionó productos entregados',
                                'paso_9_sonrisa_entrega'   => 'Sonrisa en la entrega',
                                'paso_10_despedida_cordial'=> 'Despedida cordial',
                            ];
                            foreach ($detalleJson['grupos'] as $grupoKey => $grupoData):
                                $gl = $grupoLabels[$grupoKey] ?? ['label'=>ucfirst($grupoKey),'icon'=>'📌','color'=>'#555'];
                                $calGrupo = isset($grupoData['cal_grupo']) && $grupoData['cal_grupo'] !== null ? (int)$grupoData['cal_grupo'] : null;
                                $pasos = $grupoData['pasos'] ?? [];
                            ?>
                            <div class="mb-3">
                                <div class="d-flex align-items-center gap-2 px-2 py-2 mb-1" style="background:#f8fffe;border-radius:8px;border-left:3px solid <?php echo $gl['color']; ?>;">
                                    <span><?php echo $gl['icon']; ?></span>
                                    <strong style="color:<?php echo $gl['color']; ?>;font-size:13px;"><?php echo $gl['label']; ?></strong>
                                    <?php if ($calGrupo !== null): ?>
                                        <?php $gc = $calGrupo >= 8 ? '#059669' : ($calGrupo >= 5 ? '#d97706' : '#dc3545'); ?>
                                        <span class="ms-auto" style="font-size:18px;font-weight:800;color:<?php echo $gc; ?>"><?php echo $calGrupo; ?><span style="font-size:11px;color:#adb5bd">/10</span></span>
                                    <?php else: ?>
                                        <span class="ms-auto" style="font-size:12px;color:#adb5bd">N/A</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($pasos)): ?>
                                <div class="table-responsive">
                                <table class="table table-sm tabla-lineas mb-0">
                                    <tbody>
                                    <?php foreach ($pasos as $pasoKey => $pasoData):
                                        $cal = isset($pasoData['cal']) && $pasoData['cal'] !== null ? (int)$pasoData['cal'] : null;
                                        $obs = $pasoData['obs'] ?? '—';
                                        $nc  = $cal === null ? '#adb5bd' : ($cal >= 8 ? '#059669' : ($cal >= 5 ? '#d97706' : '#dc3545'));
                                        $nombre = $pasoLabels[$pasoKey] ?? str_replace('_', ' ', $pasoKey);
                                    ?>
                                    <tr>
                                        <td style="font-size:12px;color:#555;width:170px;"><?php echo htmlspecialchars($nombre); ?></td>
                                        <td style="width:70px;text-align:center;">
                                            <?php if ($cal !== null): ?>
                                                <span style="font-weight:800;font-size:15px;color:<?php echo $nc; ?>"><?php echo $cal; ?></span>
                                                <span style="color:#adb5bd;font-size:10px">/10</span>
                                            <?php else: ?>
                                                <span style="color:#adb5bd;font-size:12px">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px;color:#555;"><?php echo htmlspecialchars($obs); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

<?php else: ?>
                        <div class="estado-placeholder">
                            <div class="icon-big">❓</div>
                            <h4>Estado desconocido</h4>
                            <p>No se encontró información de análisis para este pedido.</p>
                        </div>
<?php endif; ?>

                    </div>
                    <!-- /SECCIÓN 3 -->
<?php endif; // puedeAnalizarBot ?>

<?php endif; // pedidoGlobal ?>

                </div><!-- /detalle-wrapper -->
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════ -->
    <!-- Modal: Protocolo Oficial de Atención al Cliente Pitaya -->
    <!-- ══════════════════════════════════════════════════════ -->
    <div class="modal fade" id="modalProtocolo" tabindex="-1" aria-labelledby="modalProtocoloLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
          <div class="modal-header" style="background:linear-gradient(135deg,#0E544C,#1a7a6e);color:white;border:none;padding:20px 28px;">
            <div>
              <h5 class="modal-title fw-bold mb-1" id="modalProtocoloLabel"><i class="bi bi-clipboard-check me-2"></i>Protocolo Oficial de Atención al Cliente</h5>
              <p class="mb-0" style="font-size:12px;opacity:.8;">Pitaya · 10 pasos · Los pasos marcados con * se pueden acortar si hay fila larga</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body p-0">
            <table class="table table-hover mb-0" style="font-size:13px;">
              <thead style="position:sticky;top:0;z-index:1;">
                <tr style="background:#0E544C;color:white;">
                  <th style="width:200px;padding:12px 16px;">Etapa del servicio</th>
                  <th style="padding:12px 16px;">Criterios de evaluación</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $protocolo = [
                  ['paso'=>'1. Saluda con una Sonrisa','color'=>'#0E544C','icon'=>'🤝','criterios'=>[
                    'El colaborador debe saludar de forma inmediata a la entrada del cliente en la tienda.',
                    'Con una sonrisa genuina y contacto visual, transmitiendo energía positiva y cercanía.',
                    'El saludo debe hacer sentir al cliente bienvenido desde el primer momento.',
                  ]],
                  ['paso'=>'2. Escucha y recomienda *','color'=>'#1d4ed8','icon'=>'💬','criterios'=>[
                    'Escuchar activamente al cliente, identificar sus gustos o necesidades y recomendar productos de forma natural.',
                    'Es el momento ideal para recomendar la promoción o combo de temporada.',
                  ]],
                  ['paso'=>'3. Especifica detalles','color'=>'#0369a1','icon'=>'📝','criterios'=>[
                    'Ofrecer opciones de personalización permitidas: tipo de endulzante (sirope de azúcar morena, miel de abeja, Stevia, Splenda o sin endulzante). En caso de wafle, indicar con qué topping va acompañado.',
                  ]],
                  ['paso'=>'4. Ofrece un acompañante','color'=>'#b45309','icon'=>'🍪','criterios'=>[
                    'Sugerir de forma amable un acompañante para complementar la experiencia, como galletas de avena o frutos secos marca Pitaya, o una promoción vigente.',
                    'Sin presionar al cliente.',
                  ]],
                  ['paso'=>'5. Solicita su código y nombre *','color'=>'#6d28d9','icon'=>'⭐','criterios'=>[
                    'Preguntar si el cliente cuenta con membresía del Club Pitaya.',
                    'Si no tiene, explicar brevemente los beneficios y ofrecer la membresía como opción.',
                  ]],
                  ['paso'=>'6. Pide y cobra','color'=>'#b45309','icon'=>'💳','criterios'=>[
                    'Solicitar el nombre del cliente para la orden.',
                    'Proceder con el cobro indicando el monto total y el método de pago disponible (efectivo, tarjeta, transferencia, canje de puntos).',
                  ]],
                  ['paso'=>'7. Repite su Orden','color'=>'#065f46','icon'=>'🔁','criterios'=>[
                    'Confirmar y repetir exactamente lo que el cliente ordenó. Garantiza que el cliente esté claro y sin dudas de lo que pidió.',
                  ]],
                  ['paso'=>'8. Pregunta por la propina','color'=>'#be185d','icon'=>'🙏','criterios'=>[
                    'Es obligatorio siempre preguntar al cliente si desea agregar propina. Además es una manera de medir si se está dando un buen servicio.',
                  ]],
                  ['paso'=>'8. Cancelación de Orden','color'=>'#b45309','icon'=>'🧾','criterios'=>[
                    'Confirma monto a pagar y da a conocer los métodos de pago disponibles para que el cliente elija.',
                    'Confirmar que la factura y la comanda hayan sido generadas correctamente.',
                    'Siempre entregar la factura al cliente.',
                    'Siempre entregar la comanda de preparación a las estaciones correspondientes.',
                  ]],
                  ['paso'=>'9. Entrega y Repite','color'=>'#0E544C','icon'=>'🎁','criterios'=>[
                    'Entregar el producto llamando al cliente por su nombre, con una sonrisa, mencionando los productos que se entregan.',
                    'Cuidar la presentación del producto.',
                    'Siempre con una sonrisa.',
                  ]],
                  ['paso'=>'10. Despide','color'=>'#065f46','icon'=>'👋','criterios'=>[
                    'Agradecer la visita de forma cordial e invitar al cliente a regresar, reforzando una experiencia positiva y memorable.',
                  ]],
                ];
                foreach ($protocolo as $i => $p):
                  $bg = $i % 2 === 0 ? '#ffffff' : '#f8fffe';
                ?>
                <tr style="background:<?php echo $bg; ?>;">
                  <td style="padding:14px 16px;vertical-align:top;">
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:700;color:<?php echo $p['color']; ?>;">
                      <?php echo $p['icon']; ?> <?php echo htmlspecialchars($p['paso']); ?>
                    </span>
                  </td>
                  <td style="padding:14px 16px;vertical-align:top;">
                    <ol style="margin:0;padding-left:18px;">
                      <?php foreach ($p['criterios'] as $c): ?>
                      <li style="margin-bottom:4px;line-height:1.5;"><?php echo htmlspecialchars($c); ?></li>
                      <?php endforeach; ?>
                    </ol>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="modal-footer" style="background:#f8fffe;border-top:1px solid #e0f0ee;padding:12px 20px;">
            <p class="text-muted mb-0" style="font-size:11px;flex:1;">
              <i class="bi bi-info-circle me-1"></i>
              <strong>*Importante:</strong> Los pasos marcados con * se pueden acortar o saltar solo si hay fila muy larga. Luego, vuelve y completa lo pendiente.
            </p>
            <button type="button" class="btn btn-sm" style="background:#0E544C;color:white;border-radius:8px;" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
    <!-- /Modal Protocolo -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh si el análisis IA está pendiente o procesando
        <?php if ($datosIa && ($datosIa['estado'] === 'pendiente' || $datosIa['estado'] === 'procesando')): ?>
        setTimeout(function () {
            window.location.reload();
        }, 8000);
        <?php endif; ?>
    </script>
</body>
</html>
