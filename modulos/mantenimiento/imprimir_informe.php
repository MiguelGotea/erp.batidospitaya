<?php
// imprimir_informe.php

require_once 'models/Ticket.php';
require_once '../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo "Acceso denegado";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$ticketModel = new Ticket();
$informe = $ticketModel->getDetalleInformeCompleto($id);

if (!$informe) {
    echo "Informe no encontrado";
    exit;
}

// Lógica de Viáticos (Simplificada para el reporte)
function calcularViaticos($visitas) {
    if (empty($visitas)) return ['desayuno' => 0, 'almuerzo' => 0, 'cena' => 0];
    
    $primera = $visitas[0]['hora_llegada'];
    $ultima = end($visitas)['hora_salida'] ?? end($visitas)['hora_llegada'];
    
    $fueraManagua = false;
    foreach($visitas as $v) if ($v['departamento_sucursal'] !== 'Managua') $fueraManagua = true;

    return [
        'desayuno' => (strtotime($primera) < strtotime('06:00:00')) ? 1 : 0,
        'almuerzo' => ($fueraManagua) ? 1 : 0,
        'cena' => (strtotime($ultima) > strtotime('19:00:00')) ? 1 : 0
    ];
}

$viaticos = calcularViaticos($informe['visitas']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe Diario - <?= $informe['fecha'] ?> - <?= $informe['Nombre'] ?></title>
    <style>
        @page { size: letter; margin: 15mm; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #333; line-height: 1.4; margin: 0; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0E544C; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { height: 50px; }
        .title { font-size: 16pt; font-weight: bold; color: #0E544C; margin: 0; }
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info-item label { display: block; font-size: 8pt; color: #666; text-transform: uppercase; font-weight: bold; }
        .info-item span { font-size: 11pt; font-weight: 600; }
        
        .visita-header { background: #0E544C; color: white; padding: 8px 15px; border-radius: 5px 5px 0 0; font-weight: bold; margin-top: 25px; display: flex; justify-content: space-between; }
        .visita-body { border: 1px solid #ddd; border-top: none; padding: 15px; border-radius: 0 0 5px 5px; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; font-size: 9pt; color: #666; border-bottom: 1px solid #eee; padding: 5px; }
        td { padding: 8px 5px; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
        
        .foto-container { display: flex; gap: 5px; margin-top: 5px; flex-wrap: wrap; }
        .foto-box { width: 80px; height: 80px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
        .foto-box img { width: 100%; height: 100%; object-fit: cover; }
        
        .viatico-badge { padding: 2px 8px; border-radius: 4px; font-size: 8pt; font-weight: bold; margin-right: 5px; border: 1px solid #ddd; }
        .viatico-active { background: #e8f5e9; color: #2e7d32; border-color: #2e7d32; }

        .footer { margin-top: 40px; display: flex; justify-content: space-around; text-align: center; }
        .firma-line { border-top: 1px solid #000; width: 200px; margin-top: 40px; }

        @media print {
            .no-print { display: none; }
            body { margin: 0; }
            .visita-body { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background: #333; color: white; padding: 10px; text-align: center;">
        <button onclick="window.print()" style="padding: 8px 20px; cursor: pointer; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: bold;">
            <i class="fas fa-print"></i> IMPRIMIR AHORA
        </button>
    </div>

    <div class="header">
        <div>
            <p class="title">INFORME DIARIO DE MANTENIMIENTO</p>
            <p style="margin: 5px 0 0 0; color: #666;">Sistema ERP Pitaya - Control Operativo</p>
        </div>
        <img src="../../core/assets/img/logo_pitaya.png" class="logo" onerror="this.src='/assets/img/logo.png'">
    </div>

    <div class="info-grid">
        <div class="info-item">
            <label>Colaborador</label>
            <span><?= htmlspecialchars($informe['Nombre'] . ' ' . $informe['Apellido']) ?></span>
        </div>
        <div class="info-item">
            <label>Fecha del Reporte</label>
            <span><?= date('d/m/Y', strtotime($informe['fecha'])) ?></span>
        </div>
        <div class="info-item">
            <label>Estado</label>
            <span style="color: <?= $informe['estado'] === 'finalizado' ? '#28a745' : '#fd7e14' ?>">
                <?= strtoupper($informe['estado']) ?>
            </span>
        </div>
        <div class="info-item">
            <label>Kilometraje (Ini/Fin)</label>
            <div style="display: flex; align-items: center; gap: 10px; margin-top: 3px;">
                <div style="text-align: center;">
                    <span style="display: block; font-size: 10pt;"><?= number_format($informe['km_inicial'], 2) ?></span>
                    <?php if ($informe['km_foto_inicial']): ?>
                        <div class="foto-box" style="width: 60px; height: 60px; margin: 2px auto;">
                            <img src="uploads/informes/<?= $informe['km_foto_inicial'] ?>">
                        </div>
                    <?php endif; ?>
                </div>
                <div style="color: #ddd;">|</div>
                <div style="text-align: center;">
                    <span style="display: block; font-size: 10pt;"><?= $informe['km_final'] ? number_format($informe['km_final'], 2) : '---' ?></span>
                    <?php if ($informe['km_foto_final']): ?>
                        <div class="foto-box" style="width: 60px; height: 60px; margin: 2px auto;">
                            <img src="uploads/informes/<?= $informe['km_foto_final'] ?>">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="info-item">
            <label>Caja Chica</label>
            <span style="color: #2e7d32;">C$<?= number_format($informe['monto_caja_chica'], 2) ?></span>
        </div>
        <div class="info-item">
            <label>Viáticos Aplicados</label>
            <div style="margin-top: 3px;">
                <span class="viatico-badge <?= $viaticos['desayuno'] ? 'viatico-active' : '' ?>">Desayuno</span>
                <span class="viatico-badge <?= $viaticos['almuerzo'] ? 'viatico-active' : '' ?>">Almuerzo</span>
                <span class="viatico-badge <?= $viaticos['cena'] ? 'viatico-active' : '' ?>">Cena</span>
            </div>
        </div>
    </div>

    <h3 style="border-bottom: 1px solid #eee; padding-bottom: 5px; color: #0E544C;">RESUMEN DE VISITAS Y TRABAJOS</h3>

    <?php foreach($informe['visitas'] as $v): ?>
        <div class="visita-header">
            <span><?= htmlspecialchars($v['nombre_sucursal']) ?> (<?= $v['departamento_sucursal'] ?>)</span>
            <span><?= date('H:i', strtotime($v['hora_llegada'])) ?> - <?= $v['hora_salida'] ? date('H:i', strtotime($v['hora_salida'])) : '--' ?></span>
        </div>
        <div class="visita-body">
            <strong>Tareas Realizadas:</strong>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%">Código</th>
                        <th style="width: 25%">Asunto</th>
                        <th style="width: 40%">Detalle del Trabajo</th>
                        <th style="width: 20%">Evidencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($v['tareas'] as $t): ?>
                        <tr>
                            <td><?= $t['codigo'] ?> <br><small><?= $t['completado_100'] ? '100%' : 'PARCIAL' ?></small></td>
                            <td><?= htmlspecialchars($t['titulo']) ?></td>
                            <td><?= nl2br(htmlspecialchars($t['trabajo_realizado'])) ?></td>
                            <td>
                                <div class="foto-container">
                                    <?php foreach($t['fotos'] as $f): ?>
                                        <div class="foto-box">
                                            <img src="uploads/evidencias/<?= $f['foto'] ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if(!empty($v['compras'])): ?>
                <div style="margin-top: 15px; border-top: 1px dashed #ddd; padding-top: 10px;">
                    <strong>Compras y Facturas:</strong>
                    <table>
                        <?php foreach($v['compras'] as $c): ?>
                            <tr>
                                <td style="width: 70%"><?= htmlspecialchars($c['detalle']) ?></td>
                                <td style="width: 20%; text-align: right; font-weight: bold;">C$<?= number_format($c['monto'], 2) ?></td>
                                <td style="width: 10%; text-align: right;">
                                    <div class="foto-box" style="width: 40px; height: 40px; display: inline-block;">
                                        <img src="uploads/compras/<?= $c['foto_factura'] ?>">
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>

            <?php if($v['materiales_stock']): ?>
                <div style="margin-top: 10px; font-size: 9pt;">
                    <strong>Materiales Stock:</strong> <?= htmlspecialchars($v['materiales_stock']) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="footer">
        <div>
            <div class="firma-line"></div>
            <p>Firma Colaborador</p>
        </div>
        <div>
            <div class="firma-line"></div>
            <p>Visto Bueno (Admin/Caja)</p>
        </div>
    </div>

    <script>
        // Auto-imprimir si se desea, o dejar al botón no-print
        // window.onload = () => { window.print(); }
    </script>
</body>
</html>
