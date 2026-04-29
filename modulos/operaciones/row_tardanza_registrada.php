<?php
$nombreCompleto = trim(
    $tardanza['operario_nombre'] . ' ' . 
    ($tardanza['operario_nombre2'] ?? '') . ' ' . 
    $tardanza['operario_apellido'] . ' ' . 
    ($tardanza['operario_apellido2'] ?? '')
);

$horariosInfo = obtenerInformacionHorariosTardanza(
    $tardanza['cod_operario'], 
    $tardanza['fecha_tardanza']
);
?>

<tr id="tardanza-row-<?= $tardanza['id'] ?>" class="tardanza-registrada">
    <td><?= htmlspecialchars($tardanza['operario_nombre'] . ' ' . 
            $tardanza['operario_apellido'] . 
            ($tardanza['operario_apellido2'] ? ' ' . $tardanza['operario_apellido2'] : '')) ?></td>
    <td><?= htmlspecialchars($tardanza['sucursal_nombre']) ?></td>
    <td data-order="<?= $tardanza['fecha_tardanza'] ?>"><?= formatoFechaCorta($tardanza['fecha_tardanza']) ?></td>
    <td>
        <div><strong>Programado:</strong> <?= $horariosInfo['entrada_programada'] ?> - <?= $horariosInfo['salida_programada'] ?></div>
        <div><strong>Marcado:</strong> <?= $horariosInfo['entrada_marcada'] ?> - <?= $horariosInfo['salida_marcada'] ?></div>
    </td>
    <td><?= $tardanza['tipo_justificacion'] ? ucfirst(str_replace('_', ' ', $tardanza['tipo_justificacion'])) : '-' ?></td>
    <td data-order="<?= $tardanza['estado'] ?>">
        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $tardanza['estado'])) ?>" 
              id="status-badge-<?= $tardanza['id'] ?>">
            <?= $tardanza['estado'] ?>
        </span>
    </td>
    <td>
        <div class="observaciones-cell" id="obs-display-<?= $tardanza['id'] ?>">
            <?php if ($tardanza['observaciones']): ?>
                <?= nl2br(htmlspecialchars($tardanza['observaciones'])) ?>
            <?php else: ?>
                <span class="text-muted">Sin observaciones</span>
            <?php endif; ?>
        </div>
        <textarea 
            id="obs-edit-<?= $tardanza['id'] ?>" 
            class="observaciones-edit" 
            style="display: none;"
            rows="3"
        ><?= htmlspecialchars($tardanza['observaciones'] ?? '') ?></textarea>
    </td>
    <td><?= $tardanza['registrador_nombre'] ? htmlspecialchars($tardanza['registrador_nombre'] . ' ' . $tardanza['registrador_apellido']) : '-' ?></td>
    <td style="text-align: center;">
        <?php if (!empty($tardanza['foto_path'])): ?>
            <button type="button" 
                    onclick="mostrarFotoAmpliadaDesdeTabla('<?= $tardanza['foto_path'] ?>')" 
                    class="btn-foto"
                    title="Ver foto">
                <i class="fas fa-camera" style="color: #51B8AC; font-size: 18px;"></i>
            </button>
        <?php else: ?>
            <i class="fas fa-camera" style="color: #ccc; font-size: 18px;" title="Sin foto"></i>
        <?php endif; ?>
    </td>
    
    <?php if ($esAdmin || verificarAccesoCargo([11, 13, 16, 28, 39, 30, 37])): ?>
        <td style="text-align: center;">
            <div class="action-buttons-inline" id="actions-<?= $tardanza['id'] ?>">
                <?php if ($tardanza['estado'] === 'Pendiente'): ?>
                    <button type="button" class="btn-action btn-approve" onclick="actualizarEstado(<?= $tardanza['id'] ?>, 'Justificado')" title="Aprobar">
                        <i class="fas fa-check"></i>
                    </button>
                    <button type="button" class="btn-action btn-reject" onclick="actualizarEstado(<?= $tardanza['id'] ?>, 'No Válido')" title="Rechazar">
                        <i class="fas fa-times"></i>
                    </button>
                    <button type="button" class="btn-action btn-edit" onclick="toggleEditObservaciones(<?= $tardanza['id'] ?>)" title="Editar observaciones">
                        <i class="fas fa-edit"></i>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-action btn-change" onclick="cambiarEstado(<?= $tardanza['id'] ?>, '<?= $tardanza['estado'] ?>')" title="Cambiar estado">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    <button type="button" class="btn-action btn-edit" onclick="toggleEditObservaciones(<?= $tardanza['id'] ?>)" title="Editar observaciones">
                        <i class="fas fa-edit"></i>
                    </button>
                <?php endif; ?>
            </div>
            
            <div class="save-cancel-buttons" id="save-cancel-<?= $tardanza['id'] ?>" style="display: none;">
                <button type="button" class="btn-action btn-save" onclick="guardarObservaciones(<?= $tardanza['id'] ?>)" title="Guardar">
                    <i class="fas fa-save"></i>
                </button>
                <button type="button" class="btn-action btn-cancel" onclick="cancelarEditObservaciones(<?= $tardanza['id'] ?>)" title="Cancelar">
                    <i class="fas fa-ban"></i>
                </button>
            </div>
        </td>
    <?php endif; ?>
</tr>