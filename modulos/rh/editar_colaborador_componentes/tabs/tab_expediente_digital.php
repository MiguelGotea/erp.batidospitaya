<?php if (tienePermiso('editar_colaborador', 'edicion', $cargoId)): ?>
    <div id="expediente-digital" class="tab-pane <?= $pestaña_activa == 'expediente-digital' ? 'active' : '' ?>">
        <?php
        $expedienteCompleto = obtenerExpedienteCompletoConFaltantes($codOperario);

        $totalObligatorios = 0;
        $totalSubidos = 0;
        foreach ($expedienteCompleto as $pestana) {
            $totalObligatorios += $pestana['stats']['total_obligatorios'];
            $totalSubidos += $pestana['stats']['subidos_obligatorios'];
        }
        $porcentajeGlobal = $totalObligatorios > 0 ? round(($totalSubidos / $totalObligatorios) * 100) : 100;
        ?>

        <!-- Resumen Global del Expediente -->
        <div
            style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; border: 1px solid #eef2f3; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #0E544C;"></div>
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                <div>
                    <h3 style="color: #0E544C; margin: 0 0 5px 0; font-size: 1.5rem;">Expediente Digital</h3>
                    <p style="color: #6c757d; margin: 0;">Vista consolidada de documentos y cumplimiento por categoría.</p>
                </div>
                <div style="text-align: right;">
                    <div
                        style="font-size: 2rem; font-weight: 800; color: <?= $porcentajeGlobal >= 100 ? '#28a745' : ($porcentajeGlobal >= 70 ? '#f39c12' : '#e74c3c') ?>; line-height: 1;">
                        <?= $porcentajeGlobal ?>%
                    </div>
                    <div style="font-size: 0.85rem; color: #6c757d; font-weight: 500; margin-top: 5px;">CUMPLIMIENTO GLOBAL
                    </div>
                </div>
            </div>

            <div
                style="width: 100%; height: 12px; background: #f1f3f5; border-radius: 6px; overflow: hidden; display: flex;">
                <div
                    style="width: <?= $porcentajeGlobal ?>%; height: 100%; background: linear-gradient(90deg, #0E544C, #1a9083); border-radius: 6px; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);">
                </div>
            </div>

            <div style="display: flex; gap: 20px; margin-top: 15px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check-circle" style="color: #28a745;"></i>
                    <span style="font-size: 0.9rem; color: #495057;"><strong><?= $totalSubidos ?></strong> subidos</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle" style="color: #e74c3c;"></i>
                    <span
                        style="font-size: 0.9rem; color: #495057;"><strong><?= $totalObligatorios - $totalSubidos ?></strong>
                        faltantes obligatorios</span>
                </div>
            </div>
        </div>

        <!-- Secciones por Pestaña -->
        <div class="expediente-grid">
            <?php foreach ($expedienteCompleto as $pestanaClave => $pestana): ?>
                <div class="pestana-section" style="margin-bottom: 35px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding: 0 5px;">
                        <h4 style="margin: 0; color: #2c3e50; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                            <span
                                style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #eef2f3; border-radius: 8px; color: #0E544C;">
                                <i class="fas fa-folder"></i>
                            </span>
                            <?= htmlspecialchars($pestana['nombre']) ?>
                        </h4>
                        <div
                            style="display: flex; align-items: center; gap: 10px; background: white; padding: 5px 12px; border-radius: 20px; border: 1px solid #e9ecef; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                            <div style="width: 40px; height: 4px; background: #f1f3f5; border-radius: 2px; overflow: hidden;">
                                <div style="width: <?= $pestana['stats']['porcentaje'] ?>%; height: 100%; background: #0E544C;">
                                </div>
                            </div>
                            <span
                                style="font-size: 0.85rem; font-weight: 700; color: #0E544C; min-width: 35px;"><?= $pestana['stats']['porcentaje'] ?>%</span>
                        </div>
                    </div>

                    <div
                        style="background: white; border-radius: 10px; border: 1px solid #e9ecef; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 1px solid #e9ecef;">
                                    <th
                                        style="padding: 15px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; width: 30%;">
                                        Documento</th>
                                    <th
                                        style="padding: 15px 15px; text-align: center; font-size: 0.8rem; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; width: 12%;">
                                        Estado</th>
                                    <th
                                        style="padding: 15px 15px; text-align: center; font-size: 0.8rem; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; width: 18%;">
                                        Vencimiento</th>
                                    <th
                                        style="padding: 15px 15px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; width: 20%;">
                                        Subido / Fecha</th>
                                    <th
                                        style="padding: 15px 20px; text-align: center; font-size: 0.8rem; text-transform: uppercase; color: #7f8c8d; letter-spacing: 0.5px; width: 20%;">
                                        Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pestana['documentos'] as $doc):
                                    $estaVacio = empty($doc['archivos']);
                                    $trStyle = $estaVacio && $doc['obligatorio'] ? 'background-color: #fff9f9;' : '';
                                    ?>
                                    <tr style="border-bottom: 1px solid #f8f9fa; transition: background 0.2s; <?= $trStyle ?>"
                                        onmouseover="this.style.backgroundColor='#fcfcfc'"
                                        onmouseout="this.style.backgroundColor='<?= $estaVacio && $doc['obligatorio'] ? '#fff9f9' : 'transparent' ?>'">
                                        <td style="padding: 18px 20px;">
                                            <div
                                                style="font-weight: 600; color: #2c3e50; font-size: 0.95rem; display: flex; align-items: center; gap: 8px;">
                                                <?= htmlspecialchars($doc['nombre']) ?>
                                                <?php if ($doc['obligatorio']): ?>
                                                    <span
                                                        style="background: #e74c3c; color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase;">Obligatorio</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!$estaVacio && count($doc['archivos']) > 1): ?>
                                                <div style="font-size: 0.75rem; color: #1a9083; margin-top: 5px; font-weight: 500;">
                                                    <i class="fas fa-layer-group"></i> <?= count($doc['archivos']) ?> versiones
                                                    disponibles
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding: 18px 15px; text-align: center;">
                                            <?php if ($estaVacio): ?>
                                                <div
                                                    style="display: inline-flex; align-items: center; gap: 5px; color: #95a5a6; font-size: 0.85rem; font-weight: 500;">
                                                    <i class="fas fa-clock"></i> Pendiente
                                                </div>
                                            <?php else: ?>
                                                <div
                                                    style="display: inline-flex; align-items: center; gap: 5px; color: #2ecc71; font-size: 0.85rem; font-weight: 600;">
                                                    <i class="fas fa-check-circle"></i> Subido
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding: 18px 15px; text-align: center;">
                                            <?php
                                            if (!$estaVacio && $doc['tiene_vencimiento']) {
                                                $fechaVenc = null;
                                                foreach ($doc['archivos'] as $a) {
                                                    if (!empty($a['fecha_vencimiento'])) {
                                                        $fechaVenc = $a['fecha_vencimiento'];
                                                        break;
                                                    }
                                                }
                                                if ($fechaVenc) {
                                                    $ts = strtotime($fechaVenc);
                                                    $diff = round(($ts - time()) / 86400);
                                                    $bgColor = $diff < 0 ? '#fdecea' : ($diff < 30 ? '#fff5e6' : '#eafaf1');
                                                    $color = $diff < 0 ? '#e74c3c' : ($diff < 30 ? '#f39c12' : '#27ae60');
                                                    echo '<div style="display: inline-block; padding: 4px 10px; border-radius: 6px; background: ' . $bgColor . '; color: ' . $color . '; font-size: 0.85rem; font-weight: 600; border: 1px solid ' . $color . '22;">';
                                                    echo date('d/m/Y', $ts);
                                                    if ($diff < 0)
                                                        echo '<div style="font-size: 0.65rem; font-weight: 800; margin-top: 2px;">VENCIDO</div>';
                                                    echo '</div>';
                                                } else {
                                                    echo '<span style="color: #bdc3c7; font-size: 0.85rem; font-style: italic;">Sin fecha</span>';
                                                }
                                            } elseif ($doc['tiene_vencimiento']) {
                                                echo '<span style="color: #e67e22; font-size: 0.8rem; font-weight: 500;"><i class="fas fa-info-circle"></i> Requiere fecha</span>';
                                            } else {
                                                echo '<span style="color: #eee;">—</span>';
                                            }
                                            ?>
                                        </td>

                                        <td style="padding: 18px 15px;">
                                            <?php if (!$estaVacio): ?>
                                                <div style="font-size: 0.85rem; color: #34495e; font-weight: 500;">
                                                    <?= htmlspecialchars($doc['archivos'][0]['nombre_usuario']) ?></div>
                                                <div style="font-size: 0.75rem; color: #95a5a6;">
                                                    <?= date('d/m/Y H:i', strtotime($doc['archivos'][0]['fecha_subida'])) ?></div>
                                            <?php else: ?>
                                                <span style="color: #eee;">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="padding: 18px 20px; text-align: center;">
                                            <div style="display: flex; gap: 8px; justify-content: center;">
                                                <?php if (!$estaVacio): ?>
                                                    <?php foreach ($doc['archivos'] as $idx => $arch):
                                                        if ($idx > 1) {
                                                            if ($idx == 2)
                                                                echo '<div style="display:flex; align-items:center; color:#95a5a6; font-size:0.75rem; padding:0 5px;">+' . (count($doc['archivos']) - 2) . '</div>';
                                                            continue;
                                                        }
                                                        $ext = strtolower(pathinfo($arch['ruta_archivo'], PATHINFO_EXTENSION));
                                                        $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                        ?>
                                                        <a href="javascript:void(0)"
                                                            onclick="visualizarAdjunto('<?= htmlspecialchars($arch['ruta_archivo']) ?>')"
                                                            style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: #eef2f3; border-radius: 8px; color: #34495e; text-decoration: none; transition: all 0.2s;"
                                                            title="Ver <?= htmlspecialchars($arch['nombre_archivo']) ?>"
                                                            onmouseover="this.style.background='#0E544C'; this.style.color='white'"
                                                            onmouseout="this.style.background='#eef2f3'; this.style.color='#34495e'">
                                                            <i class="fas <?= $isImg ? 'fa-image' : 'fa-file-pdf' ?>"></i>
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <a href="javascript:void(0)" onclick="abrirModalAdjunto('<?= $pestanaClave ?>')"
                                                        style="display: flex; align-items: center; gap: 8px; background: #0E544C; color: white; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; transition: background 0.2s;"
                                                        onmouseover="this.style.background='#147066'"
                                                        onmouseout="this.style.background='#0E544C'">
                                                        <i class="fas fa-upload"></i> SUBIR
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Leyenda / Guía -->
        <div
            style="background: white; padding: 20px; border-radius: 12px; border: 1px dashed #ced4da; margin-top: 10px; display: flex; gap: 30px; align-items: center; justify-content: center; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #495057;">
                <span
                    style="background: #e74c3c; color: white; font-size: 0.6rem; padding: 1px 4px; border-radius: 3px; font-weight: 800;">OBLIGATORIO</span>
                <span>Requerido para el expediente</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #495057;">
                <div style="width: 14px; height: 14px; background: #fff9f9; border: 1px solid #f8d7da; border-radius: 3px;">
                </div>
                <span>Faltante obligatorio</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #495057;">
                <i class="fas fa-calendar-alt" style="color: #e74c3c;"></i>
                <span>Documento con fecha de vencimiento</span>
            </div>
        </div>
    </div>
<?php endif; ?>