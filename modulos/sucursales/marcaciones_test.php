<?php
// marcaciones_test.php — Test de captura DVR via AJAX (patrón Analizar)
require_once '../../core/auth/auth.php';

$usuarioActual   = obtenerUsuarioActual();
$codSucursal     = $usuarioActual['sucursal_codigo'] ?? null;
$nombreUsuario   = $usuarioActual['nombre'] ?? 'Usuario';

if (!$codSucursal) {
    die("No tienes una sucursal asignada o no estás autenticado.");
}

// Obtener datos del DVR para mostrar info en la UI
try {
    $stmt = $conn->prepare("SELECT * FROM DVR_Sucursales WHERE cod_sucursal = ? LIMIT 1");
    $stmt->execute([$codSucursal]);
    $dvr = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dvr = null;
}

$dvrIp    = $dvr['portal_ip_local'] ?? '—';
$dvrCanal = $dvr['canal_caja']      ?? 101;
$dvrOk    = !empty($dvr['portal_ip_local']) && !empty($dvr['portal_usuario']) && !empty($dvr['portal_clave']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Captura DVR — Sucursal <?= htmlspecialchars($codSucursal) ?></title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ───────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #0d1117;
            color: #e6edf3;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 16px 60px;
        }

        /* ── Tarjeta principal ─────────────────────────────── */
        .test-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 16px;
            width: 100%;
            max-width: 680px;
            overflow: hidden;
            box-shadow: 0 8px 48px rgba(0,0,0,.5);
        }

        /* ── Header ────────────────────────────────────────── */
        .card-header {
            background: linear-gradient(135deg, #0e544c 0%, #1a7a6e 100%);
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .card-header .icon-wrap {
            width: 52px; height: 52px;
            background: rgba(255,255,255,.15);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .card-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }
        .card-header p {
            font-size: .82rem;
            color: rgba(255,255,255,.75);
            margin-top: 3px;
        }

        /* ── Cuerpo ────────────────────────────────────────── */
        .card-body {
            padding: 28px 32px;
        }

        /* ── Info chips ────────────────────────────────────── */
        .info-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: .78rem;
            color: #8b949e;
        }
        .chip i { color: #51b8ac; }
        .chip strong { color: #c9d1d9; }
        .chip.dvr-ok   { border-color: #238636; }
        .chip.dvr-ok i { color: #3fb950; }
        .chip.dvr-err  { border-color: #da3633; }
        .chip.dvr-err i{ color: #f85149; }

        /* ── Selector de canal ─────────────────────────────── */
        .canal-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }
        .canal-row label {
            font-size: .85rem;
            color: #8b949e;
            white-space: nowrap;
        }
        .canal-input {
            background: #21262d;
            border: 1px solid #30363d;
            color: #c9d1d9;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: .9rem;
            width: 90px;
            transition: border-color .2s;
        }
        .canal-input:focus {
            outline: none;
            border-color: #51b8ac;
        }
        .canal-hint {
            font-size: .75rem;
            color: #6e7681;
        }

        /* ── Botón Analizar ────────────────────────────────── */
        .btn-analizar {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, #51b8ac 0%, #0e9e90 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: opacity .2s, transform .1s;
            letter-spacing: .3px;
        }
        .btn-analizar:hover:not(:disabled) {
            opacity: .9;
            transform: translateY(-1px);
        }
        .btn-analizar:active:not(:disabled) {
            transform: translateY(0);
        }
        .btn-analizar:disabled {
            opacity: .55;
            cursor: not-allowed;
        }
        .btn-analizar .spinner {
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }
        .btn-analizar.loading .spinner { display: block; }
        .btn-analizar.loading .btn-icon { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Área de resultado ─────────────────────────────── */
        #resultado {
            margin-top: 24px;
        }

        /* Estado: vacío (inicial) */
        .result-placeholder {
            border: 2px dashed #30363d;
            border-radius: 12px;
            padding: 40px 24px;
            text-align: center;
            color: #484f58;
        }
        .result-placeholder i { font-size: 2.5rem; margin-bottom: 10px; display: block; }
        .result-placeholder p  { font-size: .85rem; }

        /* Estado: error */
        .result-error {
            background: rgba(248, 81, 73, .08);
            border: 1px solid rgba(248, 81, 73, .3);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .result-error .err-icon {
            font-size: 1.4rem;
            color: #f85149;
            flex-shrink: 0;
            line-height: 1;
            margin-top: 2px;
        }
        .result-error .err-title {
            font-size: .9rem;
            font-weight: 600;
            color: #f85149;
            margin-bottom: 4px;
        }
        .result-error .err-msg {
            font-size: .82rem;
            color: #c9d1d9;
            word-break: break-word;
        }
        .result-error .err-debug {
            margin-top: 8px;
            font-family: monospace;
            font-size: .72rem;
            color: #8b949e;
            background: #0d1117;
            border-radius: 6px;
            padding: 8px;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Estado: éxito */
        .result-success { }
        .result-success .success-bar {
            background: rgba(63, 185, 80, .1);
            border: 1px solid rgba(63, 185, 80, .3);
            border-radius: 10px 10px 0 0;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .82rem;
            color: #3fb950;
            font-weight: 500;
        }
        .result-success .success-bar span { margin-left: auto; color: #6e7681; font-weight: 400; }
        .result-success .img-wrap {
            border: 1px solid #30363d;
            border-top: none;
            border-radius: 0 0 10px 10px;
            overflow: hidden;
            background: #0d1117;
            position: relative;
        }
        .result-success .img-wrap img {
            width: 100%;
            display: block;
            transition: opacity .4s ease;
        }
        .result-success .img-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .meta-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: .75rem;
            color: #8b949e;
        }
        .meta-tag i { color: #51b8ac; font-size: .8rem; }

        /* ── Historial mini ────────────────────────────────── */
        .historial-section {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #21262d;
        }
        .historial-title {
            font-size: .8rem;
            font-weight: 600;
            color: #6e7681;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 12px;
        }
        #historialLista {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .hist-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: border-color .15s;
            text-decoration: none;
        }
        .hist-item:hover { border-color: #51b8ac; }
        .hist-item i { color: #51b8ac; font-size: .95rem; }
        .hist-item .hist-nombre {
            font-size: .8rem;
            color: #c9d1d9;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .hist-item .hist-hora {
            font-size: .72rem;
            color: #6e7681;
            white-space: nowrap;
        }
        .hist-vacio {
            font-size: .8rem;
            color: #484f58;
            text-align: center;
            padding: 12px;
        }
    </style>
</head>

<body>
    <div class="test-card">

        <!-- Header -->
        <div class="card-header">
            <div class="icon-wrap">📷</div>
            <div>
                <h1>Test Captura DVR</h1>
                <p>Sucursal <strong><?= htmlspecialchars($codSucursal) ?></strong> · <?= htmlspecialchars($nombreUsuario) ?></p>
            </div>
        </div>

        <!-- Body -->
        <div class="card-body">

            <!-- Chips de info -->
            <div class="info-chips">
                <div class="chip <?= $dvrOk ? 'dvr-ok' : 'dvr-err' ?>">
                    <i class="bi bi-<?= $dvrOk ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
                    DVR: <strong><?= htmlspecialchars($dvrIp) ?></strong>
                </div>
                <div class="chip">
                    <i class="bi bi-camera-video"></i>
                    Canal caja: <strong><?= (int)$dvrCanal ?></strong>
                </div>
                <div class="chip">
                    <i class="bi bi-building"></i>
                    Sucursal: <strong><?= htmlspecialchars($codSucursal) ?></strong>
                </div>
            </div>

            <?php if (!$dvrOk): ?>
            <div class="result-error" style="margin-bottom:20px;">
                <div class="err-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="err-title">Sin configuración DVR</div>
                    <div class="err-msg">La sucursal <strong><?= htmlspecialchars($codSucursal) ?></strong> no tiene DVR configurado en la tabla <code>DVR_Sucursales</code> o faltan credenciales. Configura la IP, usuario y clave para continuar.</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Selector de canal -->
            <div class="canal-row">
                <label for="inputCanal"><i class="bi bi-camera-video" style="color:#51b8ac"></i> Canal DVR:</label>
                <input type="number" id="inputCanal" class="canal-input"
                       value="<?= (int)$dvrCanal ?>" min="1" max="999" step="1">
                <span class="canal-hint">101 = canal 1, 201 = canal 2…</span>
            </div>

            <!-- Botón Analizar -->
            <button class="btn-analizar" id="btnAnalizar" onclick="capturarImagen()"
                    <?= !$dvrOk ? 'disabled' : '' ?>>
                <div class="spinner" id="spinner"></div>
                <i class="bi bi-camera-video-fill btn-icon" id="btnIcon"></i>
                <span id="btnTexto">Analizar — Capturar Imagen DVR</span>
            </button>

            <!-- Área de resultado -->
            <div id="resultado">
                <div class="result-placeholder">
                    <i class="bi bi-image"></i>
                    <p>La imagen capturada del DVR aparecerá aquí</p>
                </div>
            </div>

            <!-- Historial de capturas de esta sesión -->
            <div class="historial-section">
                <div class="historial-title"><i class="bi bi-clock-history"></i> Capturas de esta sesión</div>
                <div id="historialLista">
                    <div class="hist-vacio">Aún no hay capturas en esta sesión.</div>
                </div>
            </div>

        </div><!-- /card-body -->
    </div><!-- /test-card -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
    // ── Estado ──────────────────────────────────────────────
    const capturasSesion = [];   // historial en memoria
    let capturaActiva    = null; // última captura exitosa

    // ── Botón Analizar ───────────────────────────────────────
    function capturarImagen() {
        const $btn    = $('#btnAnalizar');
        const canal   = parseInt($('#inputCanal').val()) || <?= (int)$dvrCanal ?>;

        // Estado: cargando
        $btn.prop('disabled', true).addClass('loading');
        $('#btnTexto').text('Conectando con DVR...');
        mostrarCargando();

        $.ajax({
            url    : 'ajax/dvr_capturar_imagen.php',
            method : 'POST',
            contentType: 'application/json',
            data   : JSON.stringify({ canal }),
            dataType: 'json',
            timeout: 20000,

            success: function(resp) {
                $btn.prop('disabled', false).removeClass('loading');

                if (resp.success) {
                    mostrarExito(resp);
                    agregarHistorial(resp);
                    $('#btnTexto').text('Analizar — Capturar Imagen DVR');
                } else {
                    mostrarError(resp.message || 'Error desconocido.', resp.debug || null);
                    $('#btnTexto').text('Reintentar');
                }
            },

            error: function(xhr, status) {
                $btn.prop('disabled', false).removeClass('loading');
                const msg = status === 'timeout'
                    ? 'Timeout: el DVR tardó demasiado en responder (>20s).'
                    : `Error de red (${status}). Verifica conectividad con el DVR.`;
                mostrarError(msg, null);
                $('#btnTexto').text('Reintentar');
            }
        });
    }

    // ── Renderizado de estados ───────────────────────────────

    function mostrarCargando() {
        $('#resultado').html(`
            <div class="result-placeholder" style="border-style:solid; border-color:#30363d;">
                <div style="display:flex;align-items:center;justify-content:center;gap:12px;color:#51b8ac;">
                    <div style="width:28px;height:28px;border:3px solid rgba(81,184,172,.3);
                                border-top-color:#51b8ac;border-radius:50%;
                                animation:spin .7s linear infinite;"></div>
                    <span style="font-size:.9rem;color:#8b949e;">Capturando imagen del DVR...</span>
                </div>
                <p style="margin-top:12px;font-size:.78rem;color:#484f58;">
                    Conectando vía ISAPI Hikvision · puede tomar hasta 15 segundos
                </p>
            </div>
        `);
    }

    function mostrarError(mensaje, debug) {
        let debugHtml = '';
        if (debug) {
            debugHtml = `<div class="err-debug">${escHtml(debug)}</div>`;
        }
        $('#resultado').html(`
            <div class="result-error">
                <div class="err-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <div class="err-title">No se pudo capturar la imagen</div>
                    <div class="err-msg">${escHtml(mensaje)}</div>
                    ${debugHtml}
                </div>
            </div>
        `);
    }

    function mostrarExito(resp) {
        capturaActiva = resp;
        const ts = resp.timestamp || new Date().toLocaleString('es');
        $('#resultado').html(`
            <div class="result-success">
                <div class="success-bar">
                    <i class="bi bi-check-circle-fill"></i>
                    Imagen capturada correctamente
                    <span>${escHtml(ts)}</span>
                </div>
                <div class="img-wrap">
                    <img src="${escHtml(resp.path)}"
                         alt="Captura DVR canal ${resp.canal}"
                         onload="this.style.opacity=1"
                         style="opacity:0;">
                </div>
                <div class="img-meta">
                    <span class="meta-tag"><i class="bi bi-hdd-network"></i> ${escHtml(resp.ip)}</span>
                    <span class="meta-tag"><i class="bi bi-camera-video"></i> Canal ${resp.canal}</span>
                    <span class="meta-tag"><i class="bi bi-building"></i> ${escHtml(resp.sucursal)}</span>
                    <span class="meta-tag"><i class="bi bi-file-earmark-image"></i> ${resp.size_kb} KB</span>
                    <a class="meta-tag" href="${escHtml(resp.path)}" target="_blank" style="color:#51b8ac; text-decoration:none;">
                        <i class="bi bi-box-arrow-up-right"></i> Abrir original
                    </a>
                </div>
            </div>
        `);
    }

    // ── Historial de sesión ──────────────────────────────────

    function agregarHistorial(resp) {
        capturasSesion.unshift(resp);
        renderHistorial();
    }

    function renderHistorial() {
        if (capturasSesion.length === 0) {
            $('#historialLista').html('<div class="hist-vacio">Aún no hay capturas en esta sesión.</div>');
            return;
        }

        const items = capturasSesion.map((c, idx) => `
            <a class="hist-item" href="${escHtml(c.path)}" target="_blank"
               title="Abrir imagen ${escHtml(c.filename)}">
                <i class="bi bi-image-fill"></i>
                <span class="hist-nombre">${escHtml(c.filename)}</span>
                <span class="hist-hora">${escHtml(c.timestamp)} · ${c.size_kb}KB</span>
            </a>
        `).join('');

        $('#historialLista').html(items);
    }

    // ── Utilidad ─────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Enter en el campo canal dispara la captura
    $('#inputCanal').on('keydown', function(e) {
        if (e.key === 'Enter') capturarImagen();
    });
    </script>
</body>
</html>