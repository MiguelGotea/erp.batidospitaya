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

        <!-- Resumen Premium Compacto -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 10px 20px; background: white; border-radius: 12px; border: 1px solid #eef2f3; box-shadow: 0 4px 6px rgba(0,0,0,0.02); position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #0E544C;"></div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="font-size: 0.9rem; color: #495057;">
                    <i class="fas fa-file-invoice" style="color: #0E544C; margin-right: 8px;"></i>
                    Documentos Obligatorios: <strong><?= $totalSubidos ?> / <?= $totalObligatorios ?></strong>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 15px; min-width: 300px;">
                <div
                    style="flex-grow: 1; height: 10px; background: #f1f3f5; border-radius: 5px; overflow: hidden; border: 1px solid #eee;">
                    <div
                        style="width: <?= $porcentajeGlobal ?>%; height: 100%; background: linear-gradient(90deg, #0E544C, #1a9083); border-radius: 5px;">
                    </div>
                </div>
                <div style="text-align: right;">
                    <span
                        style="font-weight: 800; color: #0E544C; font-size: 1.2rem; line-height: 1;"><?= $porcentajeGlobal ?>%</span>
                    <div
                        style="font-size: 0.65rem; color: #95a5a6; font-weight: 700; text-transform: uppercase; margin-top: 2px;">
                        Cumplimiento</div>
                </div>
            </div>
        </div>

        <!-- Tabla Única Premium -->
        <div
            style="background: white; border-radius: 12px; border: 1px solid #e9ecef; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.04);">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="background: #0E544C;">
                        <th
                            style="padding: 14px 20px; text-align: left; color: white; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; width: 35%;">
                            Documento</th>
                        <th
                            style="padding: 14px 15px; text-align: left; color: white; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; width: 15%;">
                            Archivos Subidos</th>
                        <th
                            style="padding: 14px 15px; text-align: center; color: white; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; width: 15%;">
                            Vencimiento</th>
                        <th
                            style="padding: 14px 15px; text-align: left; color: white; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; width: 20%;">
                            Subido Por</th>
                        <th
                            style="padding: 14px 20px; text-align: center; color: white; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; width: 15%;">
                            Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expedienteCompleto as $pestanaClave => $pestana): ?>
                        <!-- Fila de Encabezado de Grupo (Premium Style) -->
                        <tr style="background: #f8fbfb;">
                            <td colspan="5"
                                style="padding: 10px 20px; border-bottom: 2px solid #eef2f3; border-top: 1px solid #eef2f3;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div
                                        style="color: #0E544C; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px;">
                                        <div
                                            style="width: 28px; height: 28px; background: white; border-radius: 6px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #eef2f3;">
                                            <i class="fas fa-folder" style="font-size: 0.85rem;"></i>
                                        </div>
                                        <?= htmlspecialchars($pestana['nombre']) ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div
                                            style="width: 100px; height: 4px; background: #eef2f3; border-radius: 2px; overflow: hidden;">
                                            <div
                                                style="width: <?= $pestana['stats']['porcentaje'] ?>%; height: 100%; background: #0E544C;">
                                            </div>
                                        </div>
                                        <span
                                            style="font-size: 0.75rem; font-weight: 800; color: #1a9083;"><?= $pestana['stats']['porcentaje'] ?>%</span>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <?php foreach ($pestana['documentos'] as $doc):
                            $estaVacio = empty($doc['archivos']);
                            $claseFaltante = (!$estaVacio) ? '' : ($doc['obligatorio'] ? 'style="background-color: #fffafa;"' : '');

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
                            <tr <?= $claseFaltante ?> style="border-bottom: 1px solid #f8f9fa;">
                                <td style="padding: 10px 20px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div
                                            style="width: 32px; height: 32px; border-radius: 8px; background: <?= $estaVacio ? '#f8f9fa' : '#eafaf1' ?>; display: flex; align-items: center; justify-content: center; color: <?= $estaVacio ? '#bdc3c7' : '#27ae60' ?>; border: 1px solid <?= $estaVacio ? '#eee' : '#27ae6033' ?>;">
                                            <i class="fas <?= $estaVacio ? 'fa-file-alt' : 'fa-check-circle' ?>"
                                                style="font-size: 0.95rem;"></i>
                                        </div>
                                        <div
                                            style="font-weight: 600; color: #2c3e50; display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                                            <?= htmlspecialchars($doc['nombre']) ?>
                                            <?php if ($doc['obligatorio']): ?>
                                                <span
                                                    style="background: #e74c3c; color: white; font-size: 0.55rem; padding: 2px 5px; border-radius: 4px; font-weight: 800; text-transform: uppercase;">Obligatorio</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td style="padding: 10px 15px;">
                                    <?php if ($estaVacio): ?>
                                        <span
                                            style="color: #95a5a6; font-size: 0.8rem; font-weight: 500; display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-ellipsis-h" style="opacity: 0.5;"></i> Pendiente
                                        </span>
                                    <?php else: ?>
                                        <span
                                            style="color: #27ae60; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; gap: 5px; background: #eafaf1; padding: 3px 8px; border-radius: 20px; border: 1px solid #27ae6022; width: fit-content;">
                                            <i class="fas fa-check"></i> Subido
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 10px 15px; text-align: center;">
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
                                            echo '<span style="color: ' . $color . '; font-weight: 800; font-size: 0.8rem; background: ' . $bgColor . '; padding: 2px 8px; border-radius: 6px; border: 1px solid ' . $color . '22;">' . date('d/m/y', $ts) . '</span>';
                                        } else {
                                            echo '<span style="color: #bdc3c7; font-size: 0.8rem;">—</span>';
                                        }
                                    } elseif ($doc['tiene_vencimiento']) {
                                        echo '<span style="color: #e67e22; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px;"><i class="fas fa-calendar-plus"></i> Requerida</span>';
                                    } else {
                                        echo '<span style="color: #eee;">—</span>';
                                    }
                                    ?>
                                </td>

                                <td style="padding: 10px 15px;">
                                    <?php if (!$estaVacio): ?>
                                        <div style="font-size: 0.8rem; color: #34495e; display: flex; flex-direction: column;">
                                            <strong><?= htmlspecialchars($doc['archivos'][0]['nombre_usuario']) ?></strong>
                                            <span
                                                style="color: #95a5a6; font-size: 0.7rem;"><?= date('d/m/y', strtotime($doc['archivos'][0]['fecha_subida'])) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #eee;">—</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 10px 20px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                        <?php if (!$estaVacio): ?>
                                            <a href="javascript:void(0)"
                                                onclick='visualizarAdjunto("<?= htmlspecialchars($doc['archivos'][0]['ruta_archivo']) ?>", <?= $jsonImagenesDoc ?>)'
                                                style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: #0E544C; border-radius: 8px; color: white; text-decoration: none; transition: all 0.2s;"
                                                onmouseover="this.style.background='#1a9083'; this.style.transform='scale(1.1)'"
                                                onmouseout="this.style.background='#0E544C'; this.style.transform='scale(1)'"
                                                title="Ver Archivo">
                                                <i class="fas <?= in_array(strtolower(pathinfo($doc['archivos'][0]['ruta_archivo'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? 'fa-image' : 'fa-file-pdf' ?>"
                                                    style="font-size: 0.85rem;"></i>
                                            </a>
                                            <?php if (count($doc['archivos']) > 1): ?>
                                                <div style="background: #eef2f3; color: #1a9083; font-size: 0.7rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; border: 1px solid #d1d8d7;"
                                                    title="Más versiones">
                                                    +<?= count($doc['archivos']) - 1 ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button onclick="abrirModalAdjunto('<?= $pestanaClave ?>')"
                                                style="background: #0E544C; color: white; border: none; padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 0.5px;"
                                                onmouseover="this.style.background='#1a9083'; this.style.boxShadow='0 2px 8px rgba(26,144,131,0.3)'"
                                                onmouseout="this.style.background='#0E544C'; this.style.boxShadow='none'">
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

        <!-- Leyenda Elegante -->
        <div
            style="margin-top: 20px; display: flex; gap: 30px; justify-content: center; font-size: 0.8rem; color: #7f8c8d; background: white; padding: 12px; border-radius: 10px; border: 1px dashed #ced4da;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span
                    style="width: 12px; height: 12px; background: #e74c3c; border-radius: 3px; box-shadow: 0 1px 3px rgba(231,76,60,0.3);"></span>
                <span style="font-weight: 600;">Requerido por Sistema</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-check-circle" style="color: #27ae60;"></i> <span style="font-weight: 600;">Documento
                    Validado</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-calendar-alt" style="color: #f39c12;"></i> <span style="font-weight: 600;">Porta Fecha
                    Vencimiento</span>
            </div>
        </div>
    </div>
<?php endif; ?>