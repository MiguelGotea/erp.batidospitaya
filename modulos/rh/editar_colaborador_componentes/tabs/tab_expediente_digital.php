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

        <!-- Resumen Premium Superior -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 12px 20px; background: white; border-radius: 12px; border: 1px solid #eef2f3; box-shadow: 0 4px 12px rgba(0,0,0,0.03); position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; width: 5px; height: 100%; background: #0E544C;"></div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div
                    style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #eafaf1; border-radius: 10px; color: #0E544C;">
                    <i class="fas fa-clipboard-check" style="font-size: 1.2rem;"></i>
                </div>
                <div>
                    <div style="font-size: 0.95rem; color: #2c3e50; font-weight: 700;">Cumplimiento de Expediente</div>
                    <div style="font-size: 0.8rem; color: #95a5a6;"><strong><?= $totalSubidos ?> /
                            <?= $totalObligatorios ?></strong> documentos obligatorios</div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 15px; min-width: 320px;">
                <div
                    style="flex-grow: 1; height: 8px; background: #f1f3f5; border-radius: 4px; overflow: hidden; border: 1px solid #eee;">
                    <div
                        style="width: <?= $porcentajeGlobal ?>%; height: 100%; background: linear-gradient(90deg, #0E544C, #1a9083); border-radius: 4px;">
                    </div>
                </div>
                <div style="text-align: right;">
                    <span
                        style="font-weight: 800; color: #0E544C; font-size: 1.4rem; line-height: 1;"><?= $porcentajeGlobal ?><small
                            style="font-size: 0.7rem; margin-left: 2px;">%</small></span>
                </div>
            </div>
        </div>

        <!-- Secciones por Categoría (Bloques Premium) -->
        <div class="expediente-container" style="display: flex; flex-direction: column; gap: 25px;">
            <?php foreach ($expedienteCompleto as $pestanaClave => $pestana): ?>
                <div class="categoria-block"
                    style="background: white; border-radius: 12px; border: 1px solid #e9ecef; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.03);">
                    <!-- Header del Bloque (AHORA PROTAGONISTA) -->
                    <div
                        style="padding: 12px 20px; background: #0E544C; border-bottom: 1px solid #083c36; display: flex; justify-content: space-between; align-items: center;">
                        <h4
                            style="margin: 0; color: white; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; display: flex; align-items: center; gap: 12px;">
                            <div
                                style="width: 30px; height: 30px; background: rgba(255,255,255,0.15); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-folder" style="font-size: 0.9rem;"></i>
                            </div>
                            <?= htmlspecialchars($pestana['nombre']) ?>
                        </h4>
                        <div
                            style="display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.1); padding: 5px 15px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);">
                            <div
                                style="width: 80px; height: 6px; background: rgba(255,255,255,0.2); border-radius: 3px; overflow: hidden;">
                                <div
                                    style="width: <?= $pestana['stats']['porcentaje'] ?>%; height: 100%; background: #27ae60; box-shadow: 0 0 10px rgba(39,174,96,0.5);">
                                </div>
                            </div>
                            <span
                                style="font-size: 0.8rem; font-weight: 900; color: white;"><?= $pestana['stats']['porcentaje'] ?>%</span>
                        </div>
                    </div>

                    <!-- Tabla del Bloque -->
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="background: white; border-bottom: 2px solid #f8f9fa;">
                                <th
                                    style="padding: 12px 20px; text-align: left; color: #94a3b8; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; width: 35%;">
                                    Documento</th>
                                <th
                                    style="padding: 12px 15px; text-align: left; color: #94a3b8; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; width: 15%;">
                                    Estado</th>
                                <th
                                    style="padding: 12px 15px; text-align: center; color: #94a3b8; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; width: 15%;">
                                    Vencimiento</th>
                                <th
                                    style="padding: 12px 15px; text-align: left; color: #94a3b8; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; width: 20%;">
                                    Subido Por</th>
                                <th
                                    style="padding: 12px 20px; text-align: center; color: #94a3b8; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; width: 15%;">
                                    Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pestana['documentos'] as $doc):
                                $estaVacio = empty($doc['archivos']);
                                $bgColorRow = ($estaVacio && $doc['obligatorio']) ? 'background-color: #fffafa;' : (($estaVacio) ? '' : 'background-color: white;');

                                // Lista restringida para carrusel
                                $imagenesDocumento = [];
                                foreach ($doc['archivos'] as $arch) {
                                    $ext = strtolower(pathinfo($arch['ruta_archivo'], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
                                        $imagenesDocumento[] = [
                                            'url' => $arch['ruta_archivo'],
                                            'nombre' => $doc['nombre'],
                                            'categoria' => $pestana['nombre']
                                        ];
                                    }
                                }
                                $jsonImagenesDoc = json_encode($imagenesDocumento);
                                ?>
                                <tr style="<?= $bgColorRow ?> border-bottom: 1px solid #f1f3f5;">
                                    <td style="padding: 8px 20px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div
                                                style="width: 30px; height: 30px; border-radius: 8px; background: <?= $estaVacio ? '#f8f9fa' : '#eafaf1' ?>; display: flex; align-items: center; justify-content: center; color: <?= $estaVacio ? '#bdc3c7' : '#27ae60' ?>; border: 1px solid <?= $estaVacio ? '#eee' : '#27ae6033' ?>;">
                                                <i class="fas <?= $estaVacio ? 'fa-file-alt' : 'fa-check-circle' ?>"
                                                    style="font-size: 0.9rem;"></i>
                                            </div>
                                            <div
                                                style="font-weight: 600; color: #34495e; display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">
                                                <?= htmlspecialchars($doc['nombre']) ?>
                                                <?php if ($doc['obligatorio']): ?>
                                                    <span
                                                        style="background: #e74c3c; color: white; font-size: 0.5rem; padding: 2px 5px; border-radius: 4px; font-weight: 800; text-transform: uppercase;">Obligatorio</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td style="padding: 8px 15px;">
                                        <?php if ($estaVacio): ?>
                                            <span style="color: #95a5a6; font-size: 0.75rem; font-weight: 500;">
                                                <i class="fas fa-history" style="opacity: 0.5; margin-right: 4px;"></i> Pendiente
                                            </span>
                                        <?php else: ?>
                                            <span
                                                style="color: #27ae60; font-weight: 700; font-size: 0.75rem; background: #eafaf1; padding: 2px 8px; border-radius: 20px; border: 1px solid #27ae6022;">
                                                <i class="fas fa-check-double"></i> Subido
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding: 8px 15px; text-align: center;">
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
                                                $color = $diff < 0 ? '#e74c3c' : ($diff < 30 ? '#f39c12' : '#27ae60');
                                                $bgColor = $diff < 0 ? '#fdecea' : ($diff < 30 ? '#fff5e6' : '#eafaf1');
                                                echo '<span style="color: ' . $color . '; font-weight: 800; font-size: 0.75rem; background: ' . $bgColor . '; padding: 2px 8px; border-radius: 6px; border: 1px solid ' . $color . '22;">' . date('d/m/y', $ts) . '</span>';
                                            } else {
                                                echo '<span style="color: #bdc3c7; font-size: 0.75rem;">—</span>';
                                            }
                                        } elseif ($doc['tiene_vencimiento']) {
                                            echo '<span style="color: #e67e22; font-size: 0.7rem; font-weight: 700;"><i class="fas fa-calendar-alt"></i> Requerida</span>';
                                        } else {
                                            echo '<span style="color: #f1f3f5;">—</span>';
                                        }
                                        ?>
                                    </td>

                                    <td style="padding: 8px 15px;">
                                        <?php if (!$estaVacio): ?>
                                            <div
                                                style="font-size: 0.78rem; color: #34495e; display: flex; flex-direction: column; line-height: 1.2;">
                                                <strong><?= htmlspecialchars($doc['archivos'][0]['nombre_usuario']) ?></strong>
                                                <span
                                                    style="color: #95a5a6; font-size: 0.68rem;"><?= date('d/m/y', strtotime($doc['archivos'][0]['fecha_subida'])) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #f1f3f5;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding: 8px 20px; text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                            <?php if (!$estaVacio): ?>
                                                <a href="javascript:void(0)"
                                                    onclick='visualizarAdjunto("<?= htmlspecialchars($doc['archivos'][0]['ruta_archivo']) ?>", <?= $jsonImagenesDoc ?>)'
                                                    style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #0E544C; border-radius: 8px; color: white; text-decoration: none; transition: all 0.2s;"
                                                    onmouseover="this.style.background='#1a9083'; this.style.transform='scale(1.1)'"
                                                    onmouseout="this.style.background='#0E544C'; this.style.transform='scale(1)'"
                                                    title="Ver Archivo">
                                                    <i class="fas <?= in_array(strtolower(pathinfo($doc['archivos'][0]['ruta_archivo'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'fa-image' : 'fa-file-pdf' ?>"
                                                        style="font-size: 0.8rem;"></i>
                                                </a>
                                                <?php if (count($doc['archivos']) > 1): ?>
                                                    <div style="background: #eef2f3; color: #1a9083; font-size: 0.65rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; border: 1px solid #d1d8d7;"
                                                        title="Más versiones">
                                                        +<?= count($doc['archivos']) - 1 ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button
                                                    onclick="abrirModalAdjunto('<?= $pestanaClave ?>', null, '<?= $doc['id_tipo'] ?>')"
                                                    style="background: #0E544C; color: white; border: none; padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; cursor: pointer; transition: all 0.2s; text-transform: uppercase;"
                                                    onmouseover="this.style.background='#1a9083'; this.style.boxShadow='0 2px 8px rgba(26,144,131,0.2)'"
                                                    onmouseout="this.style.background='#0E544C'; this.style.boxShadow='none'">
                                                    SUBIR
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Leyenda Elegante Bottom -->
        <div
            style="margin-top: 30px; display: flex; gap: 30px; justify-content: center; font-size: 0.8rem; color: #7f8c8d; background: #f8fbfb; padding: 15px; border-radius: 12px; border: 1px solid #e9ecef;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="width: 12px; height: 12px; background: #e74c3c; border-radius: 3px;"></span> <span
                    style="font-weight: 600;">Requerido</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 12px; height: 12px; background: #27ae60; border-radius: 3px;"></div> <span
                    style="font-weight: 600;">Documentado</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-clock" style="color: #f39c12;"></i> <span style="font-weight: 600;">Vencimiento
                    Gestionable</span>
            </div>
        </div>
    </div>
<?php endif; ?>