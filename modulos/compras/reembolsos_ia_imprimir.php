<?php
/**
 * Vista de Impresión de Reembolso (Modelo Excel)
 * Ubicación: /modulos/compras/reembolsos_ia_imprimir.php
 */

require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("ID de solicitud no proporcionado.");
}

// Obtener datos de la solicitud (incluye campos de firma)
$stmt = $conn->prepare("
    SELECT s.*, p.nombre as proveedor_nombre, cp.banco, cp.numero_cuenta, cp.titular, cp.moneda as cuenta_moneda, o.Nombre as usuario_nombre,
           CONCAT(cc.Codigo, ' - ', cc.Nombre) as ceco_nombre,
           CONCAT(of.Nombre, ' ', of.Apellido) as firmante_nombre
    FROM reembolsos_solicitudes s
    LEFT JOIN proveedores p ON s.id_proveedor = p.id
    LEFT JOIN cuenta_proveedor cp ON s.id_cuenta_proveedor = cp.id
    LEFT JOIN Operarios o ON s.usuario_registro = o.CodOperario
    LEFT JOIN CentroCostos cc ON s.ceco = cc.Codigo
    LEFT JOIN Operarios of ON s.firma_firmado_por = of.CodOperario
    WHERE s.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    die("Solicitud no encontrada.");
}

// Determinar si el usuario tiene permiso de firma electrónica
$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;
$puedeFirema   = $cargoOperario && tienePermiso('reembolsos_ia_plantilla', 'firma_electronica', $cargoOperario);
$yaFirmada     = !empty($solicitud['firma_imagen']);

// Obtener detalles
$stmtDet = $conn->prepare("SELECT * FROM reembolsos_detalles WHERE id_solicitud = ?");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Imprimir Reembolso #<?= $id ?></title>
    <!-- Librerías External -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">

    <style>
        @page {
            size: letter;
            margin: 5mm 3mm; /* top/bottom 5mm, lados 3mm */
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        .container {
            max-width: 205mm;
            width: 100%;
            margin: 5mm auto;
            border: 1.5px solid #000;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.05); /* Sutil en pantalla */
            box-sizing: border-box;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed; /* Asegura que no se desborde */
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 6px;  /* reducido para ahorrar filas */
            text-align: left;
            word-wrap: break-word;
        }
        .header-title {
            text-align: center;
            font-weight: 800;
            font-size: 16px;
            background-color: #fcfcfc;
            padding: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .v-label {
            background-color: #fcfcfc;
            font-weight: bold;
            width: 80px;
            color: #444;
        }
        .v-value {
            color: #0044CC;
            font-weight: 700;
        }
        .table-main th {
            text-align: center;
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 800;
            padding: 8px;
            background-color: #f2f2f2;
            border-bottom: 2px solid #000;
        }
        .table-main td {
            height: 18px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bg-blue { color: #0044CC; }
        .total-row {
            background-color: #fcfcfc;
            font-weight: 800;
            font-size: 12px;
        }
        .footer-table td {
            border: 1px solid #000;
        }
        .no-border-top { border-top: none; }
        .no-border-bottom { border-bottom: none; }
        
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
            body { margin: 0; padding: 0; }
            .container { margin: 0 auto; width: 100%; max-width: 100%; border-left: none; border-right: none; }
            .firma-display { display: block !important; }
        }

        /* ── Firma Electrónica ───────────────────────────────────── */
        .firma-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .firma-display img {
            max-width: 140px;
            max-height: 55px;
            object-fit: contain;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }
        .firma-meta {
            font-size: 7.5px;
            color: #555;
            text-align: center;
            line-height: 1.3;
        }

        /* ── Botón Firmar ────────────────────────────────────────── */
        .btn-firmar {
            padding: 10px 20px;
            background: #2d7dd2;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-firmar:hover { background: #1f5fa8; }

        /* ── Modal Firma ─────────────────────────────────────────── */
        .modal-firma-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 12px;
        }
        .modal-firma-overlay.active { display: flex; }
        .modal-firma-box {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.35);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .modal-firma-header {
            background: #1a1a2e;
            color: #fff;
            padding: 14px 18px;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-firma-header .instruccion {
            font-size: 12px;
            font-weight: 400;
            color: #aaa;
            margin-top: 2px;
        }
        .modal-firma-body { padding: 16px; }
        #canvasFirma {
            width: 100%;
            height: 180px;
            background: #fff;
            border: 2px dashed #999;
            border-radius: 8px;
            display: block;
            cursor: crosshair;
            touch-action: none;
        }
        #canvasFirma.has-stroke { border-color: #2d7dd2; border-style: solid; }
        .modal-firma-footer {
            padding: 12px 16px;
            display: flex;
            gap: 8px;
            background: #f7f7f7;
            border-top: 1px solid #e0e0e0;
        }
        .modal-firma-footer button {
            flex: 1;
            min-height: 44px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: opacity .2s;
        }
        .modal-firma-footer button:disabled { opacity: .4; cursor: not-allowed; }
        .btn-limpiar-firma { background: #e9ecef; color: #333; }
        .btn-limpiar-firma:hover:not(:disabled) { background: #dee2e6; }
        .btn-confirmar-firma { background: #2d7dd2; color: #fff; }
        .btn-confirmar-firma:hover:not(:disabled) { background: #1f5fa8; }
        .btn-cancelar-firma { background: #6c757d; color: #fff; flex: 0 0 auto; padding: 0 16px; }
        .btn-cancelar-firma:hover { background: #5a6268; }
        @media screen and (max-width: 600px) {
            .container { margin: 2mm auto; }
            body { font-size: 10px; }
            /* En mobile: mover el panel al fondo para no tapar el documento
               y agregar scroll por si los botones no caben */
            .print-options {
                top: auto;
                bottom: 12px;
                right: 12px;
                left: 12px;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                padding: 10px 12px;
                gap: 8px;
                max-height: none;
            }
            .print-options .btn-print,
            .print-options .btn-firmar {
                flex: 1 1 auto;
                min-width: 120px;
            }
        }

        .print-options {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border: 1px solid #51B8AC;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        .btn-print {
            padding: 10px 20px;
            background: #51B8AC;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }
        .form-switch {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Layout de Imágenes */
        .page-break {
            page-break-after: always;
        }
        .photo-half {
            position: relative;
            height: 125mm;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-bottom: 1px dashed #eee;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .photo-half:last-child {
            border-bottom: none;
        }
        .photo-half img {
            max-width: 95%;
            max-height: 95%;
            object-fit: contain;
        }
        .photo-half img.rotate-90 {
            transform: rotate(90deg);
            /* Al rotar, la altura original se convierte en el ancho visual.
               Debemos asegurar que no exceda los límites. */
            max-width: 110mm; /* El alto del contenedor aprox */
            max-height: 180mm; /* El ancho del contenedor aprox */
        }
        .photo-title {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(255,255,255,0.7);
            padding: 2px 5px;
            font-size: 9px;
            border: 1px solid #ddd;
            z-index: 5;
        }

        /* Estilos para Recorte */
        .photo-half:hover .crop-overlay {
            opacity: 1;
        }
        .crop-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.2);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 10;
        }
        .btn-crop {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .btn-crop:hover {
            transform: scale(1.05);
            background: #45a498;
        }
        .btn-reset-img {
            background: #6c757d;
            margin-left: 5px;
        }
        
        .modal-crop-container {
            max-height: 70vh;
            overflow: hidden;
        }
        #image-to-crop {
            max-width: 100%;
            display: block;
        }
    </style>

</head>
<?php 
    $imprimirFotos = isset($_GET['fotos']) ? (int)$_GET['fotos'] : 1; 
    // Obtener fotos únicas y válidas
    $fotos = [];
    foreach ($detalles as $det) {
        if (!empty($det['foto_factura'])) {
            $path = '../../' . $det['foto_factura'];
            if (!in_array($path, $fotos)) {
                $fotos[] = $path;
            }
        }
    }
    
    // Decidir si la primera foto cabe en la primera hoja
    // Estimación: Header (~30mm) + Tabla (~10mm por fila) + Footer (~20mm)
    // Hoja carta: 279mm. Útil con márgenes: ~259mm. Media hoja: ~130mm.
    $numFilas = count($detalles);
    $cabeEnPrimeraHoja = ($numFilas <= 8); // Umbral más estricto para asegurar que quepa sin forzar segunda hoja

    function getPhotoClass($path) {
        if (!file_exists($path)) return '';
        $size = @getimagesize($path);
        if (!$size) return '';
        $w = $size[0];
        $h = $size[1];
        // Si es vertical (Portrait), rotarla para que use mejor el ancho de la hoja
        if ($h > $w) {
            return 'rotate-90';
        }
        return '';
    }
?>

<body onload="/*window.print()*/">

    <div class="print-options no-print">
        <label class="form-switch">
            <input type="checkbox" id="togglePhotos" <?= $imprimirFotos ? 'checked' : '' ?> onchange="cambiarPreferenciaFotos(this.checked)">
            Imprimir Fotos
        </label>
        <button class="btn-print" onclick="prepararImpresion()">
            <i class="fas fa-print me-2"></i> Imprimir
        </button>
        <?php if ($puedeFirema && !$yaFirmada): ?>
        <button class="btn-firmar" id="btnFirmarOrden" onclick="abrirModalFirma()">
            <i class="fas fa-signature"></i> Firmar Orden
        </button>
        <?php endif; ?>
        <div style="font-size: 9px; color: #666; margin-top: 5px;">
            Pasa el mouse sobre una foto para recortarla.
        </div>
    </div>


    <div id="contentToPrint">
        <div class="container <?= ($imprimirFotos && !$cabeEnPrimeraHoja && !empty($fotos)) ? 'page-break' : '' ?>">
            <table>
                <tr>
                    <td colspan="5" class="header-title">SOLICITUD DE REEMBOLSO</td>
                    <td class="text-center" style="width: 100px; color: #999;">v2-Nov24</td>
                </tr>
                <tr>
                    <td class="v-label">Fecha:</td>
                    <td class="v-value"><?= date('d-M-y', strtotime($solicitud['fecha_solicitud'])) ?></td>
                    <td class="v-label">Solicita:</td>
                    <td class="v-value"><?= htmlspecialchars($solicitud['usuario_nombre']) ?></td>
                    <td class="v-label">Autoriza:</td>
                    <td class="v-value">
                        <?php if ($yaFirmada): ?>
                        <div class="firma-display">
                            <img src="/<?= htmlspecialchars($solicitud['firma_imagen']) ?>" alt="Firma electrónica">
                            <div class="firma-meta">
                                <?= htmlspecialchars($solicitud['firmante_nombre'] ?? 'Usuario') ?><br>
                                <?= date('d/m/Y H:i', strtotime($solicitud['firma_firmado_at'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php
                // Derivar símbolo desde la moneda de la cuenta bancaria vinculada.
                // Fallback a solicitud['moneda'] para registros históricos sin cuenta.
                $monedaEfectiva = !empty($solicitud['cuenta_moneda']) ? $solicitud['cuenta_moneda'] : ($solicitud['moneda'] ?? 'Cordobas');
                $simbolo = (stripos($monedaEfectiva, 'dolar') !== false || $monedaEfectiva === 'USD') ? 'US$' : 'C$';
            ?>
            <table class="table-main">
                <thead>
                    <tr>
                        <th colspan="3"></th>
                        <th colspan="2" style="background-color: #fff; border-bottom: none;">PARA REGISTRO CONTABLE</th>
                    </tr>
                    <tr>
                        <th style="width: 8%;">CANT</th>
                        <th style="width: 42%;">DETALLE DEL GASTO</th>
                        <th style="width: 15%;">TOTAL <?= $simbolo ?></th>
                        <th style="width: 15%;">CONCEPTO (Sistema)</th>
                        <th style="width: 20%;">CECO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $det): ?>
                    <tr>
                        <td class="text-center bg-blue"><?= (float)$det['cantidad'] ?></td>
                        <td class="bg-blue"><?= htmlspecialchars($det['detalle']) ?></td>
                        <td class="text-right bg-blue"><?= number_format($det['monto_cordobas'], 2) ?></td>
                        <td class="bg-blue"><?= htmlspecialchars($solicitud['concepto']) ?></td>
                        <td class="bg-blue"><?= htmlspecialchars($solicitud['ceco_nombre'] ?? $solicitud['ceco']) ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="total-row">
                        <td colspan="2" class="text-center">TOTAL <?= $simbolo ?>:</td>
                        <td class="text-right"><?= number_format($solicitud['total_cordobas'], 2) ?></td>
                        <td colspan="2" style="border: none;"></td>
                    </tr>
                </tbody>
            </table>

            <table class="footer-table">
                <tr>
                    <td style="width: 10%;">Reembolso:</td>
                    <td style="width: 25%;" class="bg-blue">TRANSFERENCIA</td>
                    <td style="width: 10%;">Cuenta:</td>
                    <td style="width: 25%;" class="bg-blue"><?= htmlspecialchars($solicitud['titular'] ?? 'N/A') ?> - <?= htmlspecialchars($solicitud['numero_cuenta'] ?? '') ?></td>
                    <td style="width: 10%;">Banco:</td>
                    <td style="width: 20%;" class="bg-blue"><?= htmlspecialchars($solicitud['banco'] ?? 'N/A') ?> (<?= htmlspecialchars($solicitud['cuenta_moneda'] ?? '') ?>)</td>
                </tr>
            </table>

            <?php if ($imprimirFotos && $cabeEnPrimeraHoja && !empty($fotos)): ?>
                <?php 
                    $fotoActual = array_shift($fotos); 
                    $rotationClass = getPhotoClass($fotoActual);
                ?>
                <div class="photo-half" style="margin-top: 5mm; border-top: 1px dashed #ccc;">
                    <span class="photo-title">Factura Adjunta 1</span>
                    <div class="crop-overlay no-print">
                        <button class="btn-crop" onclick="abrirEditor('img_factura_0')">
                            <i class="fas fa-crop"></i> Recortar
                        </button>
                        <button class="btn-crop btn-reset-img" id="btn_reset_img_factura_0" style="display:none;" onclick="resetearImagen('img_factura_0')">
                            <i class="fas fa-undo"></i> Original
                        </button>
                    </div>
                    <img src="<?= $fotoActual ?>" id="img_factura_0" data-original="<?= $fotoActual ?>" class="<?= $rotationClass ?>" alt="Factura 1">
                </div>
            <?php endif; ?>

        </div>

        <?php if ($imprimirFotos && !empty($fotos)): ?>
            <?php 
                $chunks = array_chunk($fotos, 2); 
                foreach ($chunks as $index => $chunk):
            ?>
                <div class="page-break" style="width: 205mm; margin: 5mm auto 0; border: 1.5px solid #000; height: 265mm; background: #fff;">
                    <?php foreach ($chunk as $subIndex => $fotoPath): ?>
                        <?php 
                            $photoIndex = ($cabeEnPrimeraHoja ? $index * 2 + $subIndex + 2 : $index * 2 + $subIndex + 1);
                            $imgId = "img_factura_" . ($photoIndex - 1);
                        ?>
                        <div class="photo-half">
                            <span class="photo-title">Factura Adjunta <?= $photoIndex ?></span>
                            <div class="crop-overlay no-print">
                                <button class="btn-crop" onclick="abrirEditor('<?= $imgId ?>')">
                                    <i class="fas fa-crop"></i> Recortar
                                </button>
                                <button class="btn-crop btn-reset-img" id="btn_reset_<?= $imgId ?>" style="display:none;" onclick="resetearImagen('<?= $imgId ?>')">
                                    <i class="fas fa-undo"></i> Original
                                </button>
                            </div>
                            <img src="<?= $fotoPath ?>" id="<?= $imgId ?>" data-original="<?= $fotoPath ?>" class="<?= $rotationClass ?>" alt="Factura">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal de Firma Electrónica -->
    <?php if ($puedeFirema && !$yaFirmada): ?>
    <div class="modal-firma-overlay no-print" id="modalFirmaOverlay">
        <div class="modal-firma-box">
            <div class="modal-firma-header">
                <div>
                    <div><i class="fas fa-signature" style="margin-right:6px;"></i>Firma Electrónica</div>
                    <div class="instruccion">Firme con su dedo o mouse en el área blanca</div>
                </div>
                <button style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;" onclick="cerrarModalFirma()" title="Cerrar">&times;</button>
            </div>
            <div class="modal-firma-body">
                <canvas id="canvasFirma" width="800" height="300"></canvas>
            </div>
            <div class="modal-firma-footer">
                <button class="btn-limpiar-firma" id="btnLimpiarFirma" onclick="limpiarCanvasFirma()">Limpiar</button>
                <button class="btn-confirmar-firma" id="btnConfirmarFirma" onclick="confirmarFirma()" disabled>Confirmar Firma</button>
                <button class="btn-cancelar-firma" onclick="cerrarModalFirma()">Cancelar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal de Recorte -->
    <div class="modal fade no-print" id="modalCrop" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-crop-alt me-2"></i> Recortar Factura</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="cerrarEditor()"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="modal-crop-container">
                        <img id="image-to-crop" src="">
                    </div>
                    <div class="bg-light p-2 d-flex justify-content-center gap-2 border-top">
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="cropper.rotate(-90)" title="Rotar Izquierda">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="cropper.rotate(90)" title="Rotar Derecha">
                            <i class="fas fa-redo"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="cropper.zoom(0.1)" title="Acercar">
                            <i class="fas fa-search-plus"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="cropper.zoom(-0.1)" title="Alejar">
                            <i class="fas fa-search-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="cerrarEditor()">Cancelar</button>
                    <button type="button" class="btn btn-pitaya" style="background-color: #51B8AC; color: white;" onclick="aplicarRecorte()">
                        <i class="fas fa-check me-1"></i> Aplicar Recorte
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Librerías JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    
    <?php if ($puedeFirema && !$yaFirmada): ?>
    <script>
    /* ================================================================
       FIRMA ELECTRÓNICA — Canvas con soporte Mouse + Touch
    ================================================================ */
    (function() {
        const ORDEN_ID   = <?= (int)$id ?>;
        const ENDPOINT   = '/modulos/compras/reembolsos_firma_guardar.php';

        let canvas, ctx, isDrawing = false, hasStroke = false;
        let lastX = 0, lastY = 0;

        // ── Inicialización ──────────────────────────────────────────
        function initCanvas() {
            canvas = document.getElementById('canvasFirma');
            if (!canvas) return;
            ctx = canvas.getContext('2d');

            // Configurar trazo
            ctx.lineWidth   = 2.5;
            ctx.lineCap     = 'round';
            ctx.lineJoin    = 'round';
            ctx.strokeStyle = '#0a0a0a';
            ctx.fillStyle   = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Eventos Mouse
            canvas.addEventListener('mousedown',  startDraw);
            canvas.addEventListener('mousemove',  draw);
            canvas.addEventListener('mouseup',    stopDraw);
            canvas.addEventListener('mouseleave', stopDraw);

            // Eventos Touch (preventDefault para evitar scroll)
            canvas.addEventListener('touchstart',  onTouchStart,  { passive: false });
            canvas.addEventListener('touchmove',   onTouchMove,   { passive: false });
            canvas.addEventListener('touchend',    onTouchEnd,    { passive: false });
            canvas.addEventListener('touchcancel', onTouchEnd,    { passive: false });
        }

        // ── Mouse handlers ──────────────────────────────────────────
        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width  / rect.width;
            const scaleY = canvas.height / rect.height;
            return {
                x: (e.clientX - rect.left) * scaleX,
                y: (e.clientY - rect.top)  * scaleY
            };
        }

        function startDraw(e) {
            isDrawing = true;
            const pos = getPos(e);
            lastX = pos.x; lastY = pos.y;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        }

        function draw(e) {
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x; lastY = pos.y;
            markStroke();
        }

        function stopDraw() { isDrawing = false; }

        // ── Touch handlers ──────────────────────────────────────────
        function getTouchPos(touch) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width  / rect.width;
            const scaleY = canvas.height / rect.height;
            return {
                x: (touch.clientX - rect.left) * scaleX,
                y: (touch.clientY - rect.top)  * scaleY
            };
        }

        function onTouchStart(e) {
            e.preventDefault();
            const touch = e.changedTouches[0];
            const pos   = getTouchPos(touch);
            isDrawing = true;
            lastX = pos.x; lastY = pos.y;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        }

        function onTouchMove(e) {
            e.preventDefault();
            if (!isDrawing) return;
            const touch = e.changedTouches[0];
            const pos   = getTouchPos(touch);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x; lastY = pos.y;
            markStroke();
        }

        function onTouchEnd(e) {
            e.preventDefault();
            isDrawing = false;
        }

        // ── Detectar trazo real ─────────────────────────────────────
        function markStroke() {
            if (!hasStroke) {
                hasStroke = true;
                canvas.classList.add('has-stroke');
                document.getElementById('btnConfirmarFirma').disabled = false;
            }
        }

        // ── API Pública ─────────────────────────────────────────────
        window.abrirModalFirma = function() {
            const overlay = document.getElementById('modalFirmaOverlay');
            overlay.classList.add('active');
            // Bloquear scroll del body en mobile
            document.body.style.overflow = 'hidden';
            // Inicializar canvas al abrirlo (así tiene el tamaño correcto)
            requestAnimationFrame(() => { initCanvas(); });
        };

        window.cerrarModalFirma = function() {
            document.getElementById('modalFirmaOverlay').classList.remove('active');
            document.body.style.overflow = '';
            limpiarCanvasFirma();
        };

        window.limpiarCanvasFirma = function() {
            if (!canvas) return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            hasStroke = false;
            canvas.classList.remove('has-stroke');
            document.getElementById('btnConfirmarFirma').disabled = true;
        };

        window.confirmarFirma = async function() {
            if (!hasStroke) return;

            const btnConfirmar = document.getElementById('btnConfirmarFirma');
            const btnLimpiar   = document.getElementById('btnLimpiarFirma');
            btnConfirmar.disabled = true;
            btnLimpiar.disabled   = true;
            btnConfirmar.textContent = 'Guardando...';

            try {
                const firmaBase64 = canvas.toDataURL('image/png');

                const response = await fetch(ENDPOINT, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ id_orden: ORDEN_ID, firma_base64: firmaBase64 })
                });

                const data = await response.json();

                if (data.success) {
                    cerrarModalFirma();
                    // Recargar para mostrar la firma en el documento
                    window.location.reload();
                } else {
                    alert('Error al guardar la firma: ' + (data.error || 'Error desconocido'));
                    btnConfirmar.disabled = false;
                    btnLimpiar.disabled   = false;
                    btnConfirmar.textContent = 'Confirmar Firma';
                }
            } catch (err) {
                alert('Error de conexión al guardar la firma.');
                btnConfirmar.disabled = false;
                btnLimpiar.disabled   = false;
                btnConfirmar.textContent = 'Confirmar Firma';
            }
        };

        // Cerrar overlay al hacer clic fuera del box
        document.getElementById('modalFirmaOverlay').addEventListener('click', function(e) {
            if (e.target === this) cerrarModalFirma();
        });

    })();
    </script>
    <?php endif; ?>

    <script>
        let cropper = null;
        let currentImgId = null;
        const modalCrop = new bootstrap.Modal(document.getElementById('modalCrop'));

        function cambiarPreferenciaFotos(checked) {
            const url = new URL(window.location.href);
            url.searchParams.set('fotos', checked ? '1' : '0');
            window.location.href = url.toString();
        }

        function abrirEditor(imgId) {
            currentImgId = imgId;
            const imgTarget = document.getElementById(imgId);
            const editorImg = document.getElementById('image-to-crop');
            
            // Usar el original para el editor para no perder calidad en cada recorte
            editorImg.src = imgTarget.getAttribute('data-original');
            
            modalCrop.show();

            const image = document.getElementById('image-to-crop');
            
            // Destruir si ya existía
            if (cropper) {
                cropper.destroy();
            }

            // Timeout para esperar a que el modal se muestre y la imagen cargue
            setTimeout(() => {
                cropper = new Cropper(image, {
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.8,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                });
            }, 300);
        }

        function cerrarEditor() {
            modalCrop.hide();
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        function aplicarRecorte() {
            if (!cropper) return;

            const canvas = cropper.getCroppedCanvas({
                maxWidth: 2000,
                maxHeight: 2000,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            const croppedDataUrl = canvas.toDataURL('image/jpeg', 0.9);
            const imgTarget = document.getElementById(currentImgId);
            
            imgTarget.src = croppedDataUrl;
            
            // Al recortar, removemos la rotación automática si existía porque el usuario 
            // ya lo ajustó en el editor (o el editor lo orientó)
            imgTarget.classList.remove('rotate-90');
            
            // Mostrar botón de reset
            const resetBtn = document.getElementById('btn_reset_' + currentImgId) || document.getElementById('btn_reset_img_factura_0');
            if (resetBtn) resetBtn.style.display = 'inline-block';

            cerrarEditor();
        }

        function resetearImagen(imgId) {
            const imgTarget = document.getElementById(imgId);
            const originalSrc = imgTarget.getAttribute('data-original');
            imgTarget.src = originalSrc;
            
            // Restaurar rotación si es necesario (re-evaluar)
            // Para simplificar, si era vertical originalmente, se puede volver a poner rotate-90 
            // o simplemente refrescar la página. Pero aquí intentamos restaurar:
            // (Esta parte es opcional, depende de si queremos ser perfectos)
            location.reload(); // Recargar es más seguro para restaurar el estado inicial PHP
        }

        function prepararImpresion() {
            window.print();
        }
    </script>


</body>
</html>
