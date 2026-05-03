<?php
/**
 * atencion_cliente_detalle.php
 * Página de detalle del análisis IA de atención al cliente para un pedido.
 * Se abre en un tab separado desde historial_ventas.
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

$cod_pedido = isset($_GET['cod_pedido']) ? intval($_GET['cod_pedido']) : null;
$local      = isset($_GET['local'])      ? trim($_GET['local'])        : null;

if (!$cod_pedido || !$local) {
    die('Parámetros inválidos.');
}

// Cargar datos de la cola y resultado en una sola consulta
try {
    $stmt = $conn->prepare("
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
            a.id           AS id_analisis,
            a.cal_amabilidad,
            a.cal_saludo,
            a.cal_despedida,
            a.cal_membresia,
            a.promedio,
            a.resumen,
            a.tiene_audio,
            a.duracion_segundos,
            a.modelo_ia,
            a.created_at   AS analizado_en,
            s.nombre       AS sucursal_nombre
        FROM hikvision_cola_analisis c
        LEFT JOIN hikvision_analisis_ia_atencion a ON a.id_cola = c.id
        LEFT JOIN sucursales s ON s.codigo = c.local_codigo
        WHERE c.cod_pedido   = :cp
          AND c.local_codigo = :lc
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':cp' => $cod_pedido, ':lc' => $local]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $datos = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis IA — Pedido #<?php echo $cod_pedido; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        :root {
            --verde-pitaya: #0E544C;
            --verde-claro:  #51B8AC;
            --bg-page:      #f0f4f3;
        }
        body { background: var(--bg-page); min-height: 100vh; font-family: 'Inter', sans-serif; }

        .detalle-wrapper {
            max-width: 860px;
            margin: 32px auto;
            padding: 0 16px 60px;
        }

        /* ── Header del pedido ─────────────────────────── */
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

        .badge-estado {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
            margin-left: auto; flex-shrink: 0;
        }
        .estado-completado { background: rgba(40,167,69,0.2); color: #28a745; border: 1px solid #28a74566; }
        .estado-pendiente  { background: rgba(255,193,7,0.2);  color: #ffc107; border: 1px solid #ffc10766; }
        .estado-procesando { background: rgba(13,110,253,0.2); color: #0d6efd; border: 1px solid #0d6efd66; }
        .estado-fallido    { background: rgba(220,53,69,0.2);  color: #dc3545; border: 1px solid #dc354566; }
        .estado-sin-cola   { background: rgba(108,117,125,0.2);color: #6c757d; border: 1px solid #6c757d66; }

        /* ── Tarjetas de calificación ──────────────────── */
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
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .score-icon { font-size: 24px; margin-bottom: 8px; }
        .score-value {
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 4px;
        }
        .score-max { font-size: 13px; color: #adb5bd; }
        .score-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 12px;
            overflow: hidden;
        }
        .score-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease;
        }
        .amabilidad  .score-value   { color: #0E544C; }
        .amabilidad  .score-bar-fill{ background: linear-gradient(90deg,#51B8AC,#0E544C); }
        .saludo      .score-value   { color: #1d4ed8; }
        .saludo      .score-bar-fill{ background: linear-gradient(90deg,#3b82f6,#1d4ed8); }
        .despedida   .score-value   { color: #d97706; }
        .despedida   .score-bar-fill{ background: linear-gradient(90deg,#f59e0b,#d97706); }
        .membresia   .score-value   { color: #6d28d9; }
        .membresia   .score-bar-fill{ background: linear-gradient(90deg,#8b5cf6,#6d28d9); }
        .promedio-card .score-value { color: #059669; }
        .promedio-card .score-bar-fill{ background: linear-gradient(90deg,#10b981,#059669); }

        .score-na {
            font-size: 22px;
            font-weight: 700;
            color: #adb5bd;
            margin: 8px 0 4px;
        }

        /* ── Veredicto IA ──────────────────────────────── */
        .veredicto-card {
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .veredicto-card h5 {
            color: var(--verde-pitaya);
            font-weight: 700;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .veredicto-texto {
            font-size: 15px;
            line-height: 1.7;
            color: #343a40;
            background: #f8fffe;
            border-left: 4px solid var(--verde-claro);
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
        }

        /* ── Info técnica ──────────────────────────────── */
        .info-tecnica {
            background: white;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .info-tecnica h6 {
            color: #6c757d;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .info-item label {
            font-size: 11px;
            color: #adb5bd;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
            display: block;
        }
        .info-item span {
            font-size: 14px;
            font-weight: 600;
            color: #343a40;
        }

        /* ── Estados especiales ────────────────────────── */
        .estado-placeholder {
            background: white;
            border-radius: 14px;
            padding: 60px 32px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        }
        .estado-placeholder .icon-big {
            font-size: 56px;
            margin-bottom: 16px;
        }
        .estado-placeholder h4 { font-weight: 700; margin-bottom: 8px; }
        .estado-placeholder p  { color: #6c757d; max-width: 400px; margin: 0 auto; }

        /* Spinner */
        .spinner-ring {
            display: inline-block;
            width: 56px; height: 56px;
            border: 5px solid #e9ecef;
            border-top-color: var(--verde-claro);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Botón volver */
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

        /* Semáforo de calidad */
        .quality-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .quality-alta    { background: #d1fae5; color: #065f46; }
        .quality-media   { background: #fef3c7; color: #92400e; }
        .quality-baja    { background: #fee2e2; color: #991b1b; }

        @media (max-width: 576px) {
            .pedido-header { flex-wrap: wrap; }
            .badge-estado  { margin-left: 0; }
            .scores-grid   { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Análisis IA — Atención al Cliente'); ?>

            <div class="container-fluid p-3">
                <div class="detalle-wrapper">

                    <!-- Breadcrumb -->
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <a href="historial_ventas.php" class="btn-volver">
                            <i class="bi bi-arrow-left"></i> Volver al Historial
                        </a>
                        <span class="text-muted" style="font-size:13px;">
                            Pedido #<?php echo $cod_pedido; ?> · Local <?php echo htmlspecialchars($local); ?>
                        </span>
                    </div>

<?php if (!$datos): ?>
                    <!-- Sin registro en cola -->
                    <div class="estado-placeholder">
                        <div class="icon-big">🔍</div>
                        <h4>Sin análisis registrado</h4>
                        <p>Este pedido no ha sido encolado para análisis IA todavía. Ve al historial de ventas y presiona "Analizar Atención".</p>
                    </div>

<?php else:
    $estado      = $datos['estado'];
    $sucursal    = $datos['sucursal_nombre'] ?? "Local $local";
    $fechaFmt    = date('d/m/Y', strtotime($datos['fecha']));
    $hrInicio    = substr($datos['hora_inicio'], 0, 5);
    $hrFin       = substr($datos['hora_fin'],   0, 5);

    // Calcular calidad del promedio
    $promedio = $datos['promedio'] !== null ? (float)$datos['promedio'] : null;
    $qualityClass = 'quality-media';
    $qualityLabel = 'Media';
    if ($promedio !== null) {
        if ($promedio >= 8)      { $qualityClass = 'quality-alta';  $qualityLabel = 'Alta'; }
        elseif ($promedio < 5)   { $qualityClass = 'quality-baja';  $qualityLabel = 'Baja'; }
    }
?>
                    <!-- Header del pedido -->
                    <div class="pedido-header">
                        <div class="icon-wrap">🤖</div>
                        <div class="flex-grow-1">
                            <h1>Pedido #<?php echo $cod_pedido; ?></h1>
                            <div class="meta">
                                <span><i class="bi bi-building"></i> <?php echo htmlspecialchars($sucursal); ?></span>
                                <span><i class="bi bi-calendar3"></i> <?php echo $fechaFmt; ?></span>
                                <span><i class="bi bi-clock"></i> <?php echo $hrInicio; ?> → <?php echo $hrFin; ?></span>
                            </div>
                        </div>
                        <?php
                        $estadoBadge = [
                            'completado' => ['class' => 'estado-completado', 'icon' => '✅', 'label' => 'Completado'],
                            'pendiente'  => ['class' => 'estado-pendiente',  'icon' => '⏳', 'label' => 'En cola'],
                            'procesando' => ['class' => 'estado-procesando', 'icon' => '⚙️', 'label' => 'Analizando'],
                            'fallido'    => ['class' => 'estado-fallido',    'icon' => '❌', 'label' => 'Fallido'],
                        ];
                        $b = $estadoBadge[$estado] ?? ['class' => 'estado-sin-cola', 'icon' => '❓', 'label' => ucfirst($estado)];
                        ?>
                        <div class="badge-estado <?php echo $b['class']; ?>">
                            <?php echo $b['icon']; ?> <?php echo $b['label']; ?>
                        </div>
                    </div>

<?php if ($estado === 'pendiente' || $estado === 'procesando'): ?>
                    <!-- En proceso -->
                    <div class="estado-placeholder" id="waitingSection">
                        <div class="spinner-ring"></div>
                        <h4><?php echo $estado === 'pendiente' ? 'En cola de análisis' : 'Analizando video con IA...'; ?></h4>
                        <p>
                            <?php echo $estado === 'pendiente'
                                ? 'El pedido está esperando ser procesado por el worker. El análisis comenzará en breve.'
                                : 'El worker está descargando el video y analizando la atención al cliente con Gemini AI. Esto puede tardar 1-2 minutos.'; ?>
                        </p>
                        <p class="mt-3 text-muted" style="font-size: 12px;">
                            <i class="bi bi-arrow-repeat"></i> Esta página se actualiza automáticamente cada 8 segundos...
                        </p>
                    </div>

<?php elseif ($estado === 'fallido'): ?>
                    <!-- Error -->
                    <div class="estado-placeholder">
                        <div class="icon-big">⚠️</div>
                        <h4 style="color:#dc3545;">El análisis falló</h4>
                        <p><?php echo htmlspecialchars($datos['error_mensaje'] ?? 'Error desconocido. Revisa los logs del worker.'); ?></p>
                        <p class="mt-2 text-muted" style="font-size:12px;">Intentos: <?php echo $datos['intentos']; ?></p>
                    </div>

<?php elseif ($estado === 'completado' && $datos['id_analisis']): ?>
                    <!-- ✅ RESULTADO COMPLETO -->

                    <!-- Scores grid -->
                    <div class="scores-grid">
                        <?php
                        $metricas = [
                            ['key' => 'cal_amabilidad', 'class' => 'amabilidad',  'label' => 'Amabilidad',  'icon' => '😊'],
                            ['key' => 'cal_saludo',     'class' => 'saludo',      'label' => 'Saludo',      'icon' => '👋'],
                            ['key' => 'cal_despedida',  'class' => 'despedida',   'label' => 'Despedida',   'icon' => '🙏'],
                            ['key' => 'cal_membresia',  'class' => 'membresia',   'label' => 'Membresía',   'icon' => '⭐'],
                        ];
                        foreach ($metricas as $m):
                            $val = $datos[$m['key']] !== null ? (int)$datos[$m['key']] : null;
                            $pct = $val !== null ? ($val / 10 * 100) : 0;
                        ?>
                        <div class="score-card <?php echo $m['class']; ?>">
                            <div class="score-icon"><?php echo $m['icon']; ?></div>
                            <div class="score-label"><?php echo $m['label']; ?></div>
                            <?php if ($val !== null): ?>
                                <div class="score-value"><?php echo $val; ?></div>
                                <div class="score-max">/ 10</div>
                                <div class="score-bar">
                                    <div class="score-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                            <?php else: ?>
                                <div class="score-na">N/A</div>
                                <div class="score-max">No aplica</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <!-- Promedio -->
                        <div class="score-card promedio-card">
                            <div class="score-icon">📊</div>
                            <div class="score-label">Promedio</div>
                            <?php if ($promedio !== null): ?>
                                <div class="score-value"><?php echo number_format($promedio, 1); ?></div>
                                <div class="score-max">/ 10</div>
                                <div class="score-bar">
                                    <div class="score-bar-fill" style="width:<?php echo ($promedio/10*100); ?>%"></div>
                                </div>
                                <div class="mt-2">
                                    <span class="quality-indicator <?php echo $qualityClass; ?>">
                                        Calidad <?php echo $qualityLabel; ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="score-na">N/A</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Veredicto de la IA -->
                    <?php if (!empty($datos['resumen'])): ?>
                    <div class="veredicto-card">
                        <h5><i class="bi bi-chat-quote-fill"></i> Veredicto de la IA</h5>
                        <div class="veredicto-texto">
                            <?php echo nl2br(htmlspecialchars($datos['resumen'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Info técnica -->
                    <div class="info-tecnica">
                        <h6><i class="bi bi-info-circle"></i> Información del análisis</h6>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Modelo IA</label>
                                <span><?php echo htmlspecialchars($datos['modelo_ia'] ?? '—'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Duración video</label>
                                <span><?php echo $datos['duracion_segundos'] ? $datos['duracion_segundos'].'s' : '—'; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Audio detectado</label>
                                <span><?php echo $datos['tiene_audio'] ? '✅ Sí' : '❌ No'; ?></span>
                            </div>
                            <div class="info-item">
                                <label>Tipo de análisis</label>
                                <span><?php echo ucfirst($datos['tipo'] ?? '—'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Analizado el</label>
                                <span><?php echo $datos['analizado_en'] ? date('d/m/Y H:i', strtotime($datos['analizado_en'])) : '—'; ?></span>
                            </div>
                            <div class="info-item">
                                <label>ID Cola</label>
                                <span>#<?php echo $datos['id_cola']; ?></span>
                            </div>
                        </div>
                    </div>

<?php else: ?>
                    <div class="estado-placeholder">
                        <div class="icon-big">❓</div>
                        <h4>Estado desconocido</h4>
                        <p>No se encontró información de análisis para este pedido.</p>
                    </div>
<?php endif; ?>
<?php endif; ?>

                </div><!-- /detalle-wrapper -->
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh si el análisis está pendiente o procesando
        <?php if ($datos && ($datos['estado'] === 'pendiente' || $datos['estado'] === 'procesando')): ?>
        setTimeout(function() {
            window.location.reload();
        }, 8000);
        <?php endif; ?>
    </script>
</body>
</html>
