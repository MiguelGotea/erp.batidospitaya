<?php
$horariosInfo = [
    'entrada_programada' => $tardanza['horario_entrada_programada'],
    'salida_programada' => $tardanza['horario_salida_programada'],
    'entrada_marcada' => $tardanza['horario_entrada_marcada'],
    'salida_marcada' => $tardanza['horario_salida_marcada']
];
?>

<tr id="tardanza-nr-<?= $tardanza['cod_operario'] . '-' . $tardanza['fecha_tardanza'] ?>" class="tardanza-no-reportada" style="background-color: #fff8e1;">
    <td><?= htmlspecialchars($tardanza['operario_nombre'] . ' ' . 
            $tardanza['operario_apellido'] . 
            ($tardanza['operario_apellido2'] ? ' ' . $tardanza['operario_apellido2'] : '')) ?></td>
    <td><?= htmlspecialchars($tardanza['sucursal_nombre']) ?></td>
    <td data-order="<?= $tardanza['fecha_tardanza'] ?>">
        <?= formatoFechaCorta($tardanza['fecha_tardanza']) ?>
    </td>
    <td>
        <div><strong>Programado:</strong> <?= $horariosInfo['entrada_programada'] ?> - <?= $horariosInfo['salida_programada'] ?></div>
        <div><strong>Marcado:</strong> <?= $horariosInfo['entrada_marcada'] ?> - <?= $horariosInfo['salida_marcada'] ?></div>
        <div><small style="color: #dc3545;"><strong>Tardanza:</strong> <?= $tardanza['minutos_tardanza'] ?> minutos</small></div>
    </td>
    <td>-</td>
    <td>
        <span class="status-badge status-no-reportada" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
            No Reportada
        </span>
    </td>
    <td>
        <span class="text-muted">Tardanza detectada automáticamente</span>
    </td>
    <td>-</td>
    <td style="text-align: center;">
        <i class="fas fa-camera" style="color: #ccc; font-size: 18px;" title="Sin foto"></i>
    </td>
    
    <td style="text-align: center;">
        <div class="action-buttons-inline">
            <?php// if ($esOperaciones): ?>
                <button type="button" class="btn-action btn-registrar" 
                        onclick="registrarTardanzaNoReportada(
                            <?= $tardanza['cod_operario'] ?>, 
                            '<?= $tardanza['fecha_tardanza'] ?>',
                            '<?= htmlspecialchars($tardanza['sucursal_nombre'], ENT_QUOTES) ?>',
                            <?= $tardanza['minutos_tardanza'] ?>,
                            <?= obtenerCodigoSucursalPorNombre($tardanza['sucursal_nombre']) ?>
                        )"
                        title="Registrar esta tardanza">
                    <i class="fas fa-plus-circle"></i>
                </button>
            <?php// endif; ?>
            
            <button type="button" class="btn-action btn-info" 
                    onclick="verDetallesTardanzaNoReportada(
                        <?= $tardanza['cod_operario'] ?>, 
                        '<?= $tardanza['fecha_tardanza'] ?>',
                        '<?= $tardanza['sucursal_nombre'] ?>',
                        <?= $tardanza['minutos_tardanza'] ?>
                    )" 
                    title="Ver detalles">
                <i class="fas fa-info-circle"></i>
            </button>
        </div>
    </td>
</tr>