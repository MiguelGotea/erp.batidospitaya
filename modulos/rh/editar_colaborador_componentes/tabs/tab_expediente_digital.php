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

        <!-- Resumen Compacto -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 12px 15px; background: white; border-radius: 10px; border: 1px solid #eef2f3; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div style="display: flex; align-items: center; gap: 15px;">
                <h3 style="color: #0E544C; margin: 0; font-size: 1.3rem; font-weight: 700;">Expediente Digital</h3>
                <div style="height: 20px; width: 1px; background: #dee2e6;"></div>
                <div style="font-size: 0.9rem; color: #495057;">
                    <strong><?= $totalSubidos ?></strong> de <strong><?= $totalObligatorios ?></strong> obligatorios subidos
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 15px; min-width: 250px;">
                <div style="flex-grow: 1; height: 8px; background: #f1f3f5; border-radius: 4px; overflow: hidden;">
                    <div
                        style="width: <?= $porcentajeGlobal ?>%; height: 100%; background: linear-gradient(90deg, #0E544C, #1a9083); border-radius: 4px;">
                    </div>
                </div>
                <span style="font-weight: 800; color: #0E544C; font-size: 1.1rem;"><?= $porcentajeGlobal ?>%</span>
            </div>
        </div>

        <!-- Tabla Única y Compacta -->
        <div
            style="background: white; border-radius: 10px; border: 1px solid #e9ecef; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th
                            style="padding: 12px 15px; text-align: left; color: #7f8c8d; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; width: 35%; border-bottom: 1px solid #dee2e6;">
                            Documento</th>
                        <th
                            style="padding: 12px 15px; text-align: left; color: #7f8c8d; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; width: 15%; border-bottom: 1px solid #dee2e6;">
                            Archivos Subidos</th>
                        <th
                            style="padding: 12px 15px; text-align: center; color: #7f8c8d; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; width: 15%; border-bottom: 1px solid #dee2e6;">
                            Vencimiento</th>
                        <th
                            style="padding: 12px 15px; text-align: left; color: #7f8c8d; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; width: 20%; border-bottom: 1px solid #dee2e6;">
                            Subido Por</th>
                        <th
                            style="padding: 12px 15px; text-align: center; color: #7f8c8d; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; width: 15%; border-bottom: 1px solid #dee2e6;">
                            Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expedienteCompleto as $pestanaClave => $pestana): ?>
                        <!-- Fila de Encabezado de Grupo -->
                        <tr style="background: #f4f7f6;">
                            <td colspan="5"
                                style="padding: 8px 15px; border-bottom: 1px solid #e0e0e0; border-top: 1px solid #e0e0e0;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div
                                        style="color: #0E544C; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px;">
                                        <i class="fas fa-folder-open" style="margin-right: 8px; opacity: 0.7;"></i>
                                        <?= htmlspecialchars($pestana['nombre']) ?>
                                    </div>
                                    <div
                                        style="font-size: 0.7rem; font-weight: 600; color: #1a9083; background: white; padding: 2px 8px; border-radius: 10px; border: 1px solid #d1d8d7;">
                                        <?= $pestana['stats']['porcentaje'] ?>% CUMPLIMIENTO
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php foreach ($pestana['documentos'] as $doc):
                            $estaVacio = empty($doc['archivos']);
                            $claseFaltante = (!$estaVacio) ? '' : ($doc['obligatorio'] ? 'style="background-color: #fff9f9;"' : '');

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
                            <tr <?= $claseFaltante ?> style="border-bottom: 1px solid #f1f3f5;">
                                <td style="padding: 8px 15px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i class="fas <?= $estaVacio ? 'fa-file-alt' : 'fa-check-circle' ?>"
                                            style="color: <?= $estaVacio ? '#adb5bd' : '#27ae60' ?>; font-size: 0.9rem;"></i>
                                        <div style="font-weight: 600; color: #2c3e50;">
                                            <?= htmlspecialchars($doc['nombre']) ?>
                                            <?php if ($doc['obligatorio']): ?>
                                                <span
                                                    style="margin-left: 8px; background: #e74c3c; color: white; font-size: 0.55rem; padding: 1px 4px; border-radius: 3px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px;">OBLIGATORIO</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td style="padding: 8px 15px;">
                                    <?php if ($estaVacio): ?>
                                        <span style="color: #adb5bd; font-size: 0.75rem; font-style: italic;">Pendiente</span>
                                    <?php else: ?>
                                        <span style="color: #27ae60; font-weight: 700; font-size: 0.75rem;"><i class="fas fa-check"></i>
                                            Subido</span>
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
                                            echo '<span style="color: ' . $color . '; font-weight: 700; font-size: 0.8rem;">' . date('d/m/y', $ts) . '</span>';
                                        } else {
                                            echo '<span style="color: #bdc3c7; font-size: 0.75rem;">—</span>';
                                        }
                                    } elseif ($doc['tiene_vencimiento']) {
                                        echo '<span style="color: #e67e22; font-size: 0.7rem; font-weight: 600;"><i class="fas fa-clock"></i> Requerida</span>';
                                    } else {
                                        echo '<span style="color: #eee;">—</span>';
                                    }
                                    ?>
                                </td>

                                <td style="padding: 8px 15px;">
                                    <?php if (!$estaVacio): ?>
                                        <div style="font-size: 0.8rem; color: #34495e;">
                                            <strong><?= htmlspecialchars($doc['archivos'][0]['nombre_usuario']) ?></strong>
                                            <span
                                                style="color: #95a5a6; font-size: 0.7rem; margin-left: 5px;"><?= date('d/m/y', strtotime($doc['archivos'][0]['fecha_subida'])) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #eee;">—</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 8px 15px; text-align: center;">
                                    <div style="display: flex; gap: 5px; justify-content: center;">
                                        <?php if (!$estaVacio): ?>
                                            <?php foreach ($doc['archivos'] as $idx => $arch):
                                                if ($idx > 0)
                                                    break; // Mostrar solo el botón del último archivo para compactar
                                                $ext = strtolower(pathinfo($arch['ruta_archivo'], PATHINFO_EXTENSION));
                                                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                ?>
                                                <a href="javascript:void(0)"
                                                    onclick='visualizarAdjunto("<?= htmlspecialchars($arch['ruta_archivo']) ?>", <?= $jsonImagenesDoc ?>)'
                                                    style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #eef2f3; border-radius: 6px; color: #34495e; text-decoration: none;"
                                                    title="Ver Archivo">
                                                    <i class="fas <?= $isImg ? 'fa-image' : 'fa-file-pdf' ?>"
                                                        style="font-size: 0.85rem;"></i>
                                                </a>
                                            <?php endforeach; ?>
                                            <?php if (count($doc['archivos']) > 1): ?>
                                                <span
                                                    style="color: #1a9083; font-size: 0.7rem; font-weight: 700; align-self: center;">+<?= count($doc['archivos']) - 1 ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button onclick="abrirModalAdjunto('<?= $pestanaClave ?>')"
                                                style="background: #0E544C; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; cursor: pointer;">
                                                SUBIR
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Leyenda Compacta -->
        <div
            style="margin-top: 15px; display: flex; gap: 20px; justify-content: center; font-size: 0.75rem; color: #7f8c8d;">
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="width: 10px; height: 10px; background: #e74c3c; border-radius: 2px;"></span> Requerido
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <span style="width: 10px; height: 10px; background: #27ae60; border-radius: 2px;"></span> Subido
            </div>
            <div style="display: flex; align-items: center; gap: 5px;">
                <i class="fas fa-clock" style="color: #e67e22;"></i> Vencimiento Requerido
            </div>
        </div>
    </div>
<?php endif; ?>